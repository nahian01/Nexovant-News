from datetime import datetime, timezone, timedelta
from typing import Dict, Any


class QuotaTracker:
    """
    Real-time RPM and TPM Quota Tracker per AI connection.
    Protects against provider-specific rate limit exceptions.
    """
    def __init__(
        self,
        rpm_limit: int = 60,
        tpm_limit: int = 100000,
        daily_limit: int | None = None,
    ):
        self.rpm_limit = rpm_limit
        self.tpm_limit = tpm_limit
        self.daily_limit = daily_limit

        self.current_rpm: int = 0
        self.current_tpm: int = 0
        self.current_daily_tokens: int = 0

        self.last_minute_reset = datetime.now(timezone.utc)
        self.last_daily_reset = datetime.now(timezone.utc)

    def _check_and_reset(self) -> None:
        now = datetime.now(timezone.utc)
        # Reset minute window
        if now - self.last_minute_reset >= timedelta(minutes=1):
            self.current_rpm = 0
            self.current_tpm = 0
            self.last_minute_reset = now

        # Reset daily window
        if now - self.last_daily_reset >= timedelta(days=1):
            self.current_daily_tokens = 0
            self.last_daily_reset = now

    def can_consume(self, estimated_tokens: int = 1000) -> bool:
        self._check_and_reset()
        if self.current_rpm >= self.rpm_limit:
            return False
        if (self.current_tpm + estimated_tokens) > self.tpm_limit:
            return False
        if self.daily_limit and (self.current_daily_tokens + estimated_tokens) > self.daily_limit:
            return False
        return True

    def record_usage(self, input_tokens: int, output_tokens: int) -> None:
        self._check_and_reset()
        total_tokens = input_tokens + output_tokens
        self.current_rpm += 1
        self.current_tpm += total_tokens
        self.current_daily_tokens += total_tokens

    @property
    def rpm_saturation(self) -> float:
        self._check_and_reset()
        return round(self.current_rpm / max(self.rpm_limit, 1), 3)

    @property
    def tpm_saturation(self) -> float:
        self._check_and_reset()
        return round(self.current_tpm / max(self.tpm_limit, 1), 3)

    def to_dict(self) -> Dict[str, Any]:
        return {
            "rpm_limit": self.rpm_limit,
            "tpm_limit": self.tpm_limit,
            "current_rpm": self.current_rpm,
            "current_tpm": self.current_tpm,
            "rpm_saturation": self.rpm_saturation,
            "tpm_saturation": self.tpm_saturation,
        }
