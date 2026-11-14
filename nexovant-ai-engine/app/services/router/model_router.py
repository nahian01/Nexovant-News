import time
from typing import Dict, Any, List, Optional
from app.services.router.provider_registry import registry, AIConnection


class ModelRouter:
    """
    Intelligent Multi-Provider AI API Gateway & Router.
    Routes tasks to the optimal provider connection based on:
      1. Task compatibility
      2. Circuit breaker state
      3. Quota headroom (RPM / TPM)
      4. Cost efficiency
      5. Admin priority
    Executes automated fallback when rate limits (429) or timeouts occur.
    """
    def __init__(self, provider_registry=registry):
        self.registry = provider_registry

    def select_best_connection(self, task: str) -> Optional[AIConnection]:
        candidates = [
            conn for conn in self.registry.list_all()
            if conn.is_eligible_for_task(task)
        ]
        if not candidates:
            return None

        # Sort descending by multi-factor score
        candidates.sort(key=lambda c: c.calculate_score(), reverse=True)
        return candidates[0]

    async def execute_task(
        self,
        task: str,
        prompt: str,
        system_instruction: str = "",
        max_tokens: int = 1000,
    ) -> Dict[str, Any]:
        """
        Executes an AI task with automatic fallback across eligible provider connections.
        """
        eligible = [
            c for c in self.registry.list_all()
            if c.is_eligible_for_task(task)
        ]
        eligible.sort(key=lambda c: c.calculate_score(), reverse=True)

        if not eligible:
            raise RuntimeError(f"No active or eligible AI connections available for task '{task}'.")

        errors: List[str] = []
        attempted_connections: List[str] = []

        start_time = time.time()

        for conn in eligible:
            attempted_connections.append(conn.connection_id)
            try:
                # Simulate / execute provider adapter
                input_tokens = len(prompt.split()) + len(system_instruction.split()) + 20
                output_tokens = min(max_tokens, 250)

                # Simulated response text for cognitive pipeline
                response_text = self._mock_provider_response(task, prompt, conn.model_name)

                # Record success and token consumption
                conn.circuit_breaker.record_success()
                conn.quota_tracker.record_usage(input_tokens, output_tokens)

                cost_usd = (
                    (input_tokens / 1000.0) * conn.input_cost_per_1k +
                    (output_tokens / 1000.0) * conn.output_cost_per_1k
                )

                latency_ms = int((time.time() - start_time) * 1000)

                return {
                    "success": True,
                    "task": task,
                    "provider": conn.provider_name,
                    "model": conn.model_name,
                    "connection_id": conn.connection_id,
                    "content": response_text,
                    "usage": {
                        "input_tokens": input_tokens,
                        "output_tokens": output_tokens,
                        "estimated_cost_usd": round(cost_usd, 6),
                        "latency_ms": latency_ms,
                    },
                    "fallback_triggered": len(attempted_connections) > 1,
                    "attempted_connections": attempted_connections,
                }
            except Exception as e:
                conn.circuit_breaker.record_failure()
                errors.append(f"[{conn.connection_id}] {str(e)}")

        raise RuntimeError(f"All eligible AI provider connections failed. Errors: {'; '.join(errors)}")

    def _mock_provider_response(self, task: str, prompt: str, model_name: str) -> str:
        if task == "summarization":
            return (
                f"[{model_name}] Executive Synthesis: The analyzed corporate development represents a strategic restructuring "
                "aimed at sovereign supply-chain autonomy. Capital deployment is expected to reinforce medium-term operating margins."
            )
        elif task == "rag":
            return (
                f"[{model_name}] Grounded Intelligence Response: Based on verified filings, the entity disclosed strategic "
                "expansion milestones with positive institutional sentiment. No regulatory sanctions are currently pending."
            )
        return f"[{model_name}] Analyzed response completed for task '{task}'."


router = ModelRouter()
