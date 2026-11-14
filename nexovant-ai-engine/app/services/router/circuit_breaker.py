from datetime import datetime, timezone, timedelta
from typing import Dict, Any


class CircuitBreaker:
    """
    3-strike Circuit Breaker protecting upstream AI provider connections.
    States:
      - CLOSED: Normal operation, requests flow freely.
      - OPEN: Tripped due to failures or 429 quota exhaustion; requests blocked.
      - HALF_OPEN: Cooldown expired; canary request allowed to test connectivity.
    """
    def __init__(self, failure_threshold: int = 3, cooldown_seconds: int = 180):
        self.failure_threshold = failure_threshold
        self.cooldown_seconds = cooldown_seconds
        self.state: str = "closed" # closed, half_open, open
        self.consecutive_failures: int = 0
        self.tripped_at: datetime | None = None

    def record_success(self) -> None:
        self.consecutive_failures = 0
        self.state = "closed"
        self.tripped_at = None

    def record_failure(self, is_rate_limit: bool = False) -> None:
        self.consecutive_failures += 1
        if is_rate_limit or self.consecutive_failures >= self.failure_threshold:
            self.state = "open"
            self.tripped_at = datetime.now(timezone.utc)

    def can_execute(self) -> bool:
        if self.state == "closed":
            return True

        if self.state == "open":
            if self.tripped_at:
                elapsed = datetime.now(timezone.utc) - self.tripped_at
                if elapsed > timedelta(seconds=self.cooldown_seconds):
                    self.state = "half_open"
                    return True
            return False

        if self.state == "half_open":
            return True

        return False

    def to_dict(self) -> Dict[str, Any]:
        return {
            "state": self.state,
            "consecutive_failures": self.consecutive_failures,
            "tripped_at": self.tripped_at.isoformat() if self.tripped_at else None,
        }
