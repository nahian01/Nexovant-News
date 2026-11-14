from typing import Dict, List, Optional, Any
from app.services.router.circuit_breaker import CircuitBreaker
from app.services.router.quota_tracker import QuotaTracker


class AIConnection:
    def __init__(
        self,
        connection_id: str,
        provider_name: str,
        model_name: str,
        capabilities: List[str],
        priority: int = 1,
        rpm_limit: int = 60,
        tpm_limit: int = 100000,
        input_cost_per_1k: float = 0.0015,
        output_cost_per_1k: float = 0.0060,
        is_active: bool = True,
    ):
        self.connection_id = connection_id
        self.provider_name = provider_name
        self.model_name = model_name
        self.capabilities = [c.lower() for c in capabilities]
        self.priority = priority
        self.input_cost_per_1k = input_cost_per_1k
        self.output_cost_per_1k = output_cost_per_1k
        self.is_active = is_active

        self.circuit_breaker = CircuitBreaker()
        self.quota_tracker = QuotaTracker(rpm_limit=rpm_limit, tpm_limit=tpm_limit)

    def is_eligible_for_task(self, task: str) -> bool:
        if not self.is_active:
            return False
        if task.lower() not in self.capabilities and "general" not in self.capabilities:
            return False
        if not self.circuit_breaker.can_execute():
            return False
        if not self.quota_tracker.can_consume():
            return False
        return True

    def calculate_score(self) -> float:
        """
        Multi-factor score:
        Higher score = higher priority candidate.
        Factors:
          - Priority: Weight 40% (Priority 1 > Priority 10)
          - Quota Headroom: Weight 30% (Available RPM/TPM)
          - Cost Efficiency: Weight 30% (Lower cost = higher score)
        """
        p_score = max(0, 100 - (self.priority * 10))
        q_headroom = (1.0 - self.quota_tracker.rpm_saturation) * 100
        cost_score = max(0, 100 - (self.output_cost_per_1k * 5000))
        return (p_score * 0.40) + (q_headroom * 0.30) + (cost_score * 0.30)

    def to_dict(self) -> Dict[str, Any]:
        return {
            "connection_id": self.connection_id,
            "provider_name": self.provider_name,
            "model_name": self.model_name,
            "capabilities": self.capabilities,
            "priority": self.priority,
            "is_active": self.is_active,
            "circuit": self.circuit_breaker.to_dict(),
            "quota": self.quota_tracker.to_dict(),
            "score": round(self.calculate_score(), 2),
        }


class ProviderRegistry:
    def __init__(self):
        self._connections: Dict[str, AIConnection] = {}
        self._initialize_baseline_connections()

    def _initialize_baseline_connections(self):
        # 1. Anthropic Flagship Connection
        self.register(
            AIConnection(
                connection_id="conn-anthropic-claude35",
                provider_name="Anthropic",
                model_name="claude-3-5-sonnet",
                capabilities=["summarization", "complex_reasoning", "impact_analysis", "rag"],
                priority=1,
                rpm_limit=80,
                tpm_limit=200000,
                input_cost_per_1k=0.003,
                output_cost_per_1k=0.015,
            )
        )
        # 2. OpenAI GPT-4o Connection
        self.register(
            AIConnection(
                connection_id="conn-openai-gpt4o",
                provider_name="OpenAI",
                model_name="gpt-4o",
                capabilities=["summarization", "complex_reasoning", "entity_extraction", "rag"],
                priority=2,
                rpm_limit=100,
                tpm_limit=250000,
                input_cost_per_1k=0.0025,
                output_cost_per_1k=0.010,
            )
        )
        # 3. Google Gemini 2.0 Flash Connection
        self.register(
            AIConnection(
                connection_id="conn-google-gemini20",
                provider_name="Google Gemini",
                model_name="gemini-2.0-flash",
                capabilities=["classification", "summarization", "entity_extraction", "rag"],
                priority=3,
                rpm_limit=150,
                tpm_limit=400000,
                input_cost_per_1k=0.0001,
                output_cost_per_1k=0.0004,
            )
        )
        # 4. Groq Ultra-Fast Llama 3 Connection
        self.register(
            AIConnection(
                connection_id="conn-groq-llama3",
                provider_name="Groq",
                model_name="llama-3.3-70b-versatile",
                capabilities=["classification", "entity_extraction", "summarization"],
                priority=4,
                rpm_limit=200,
                tpm_limit=500000,
                input_cost_per_1k=0.0005,
                output_cost_per_1k=0.0008,
            )
        )

    def register(self, connection: AIConnection) -> None:
        self._connections[connection.connection_id] = connection

    def get(self, connection_id: str) -> Optional[AIConnection]:
        return self._connections.get(connection_id)

    def list_all(self) -> List[AIConnection]:
        return list(self._connections.values())


registry = ProviderRegistry()
