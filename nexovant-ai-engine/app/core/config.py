from typing import Optional
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    APP_NAME: str = "Nexovant Cognitive Engine"
    ENVIRONMENT: str = "development"
    DEBUG: bool = False
    API_V1_PREFIX: str = "/api/internal/v1"

    # Internal Authentication Header Secret
    INTERNAL_SERVICE_SECRET: str = "nexovant-internal-secret-for-dev"

    # Database Settings
    DB_HOST: str = "localhost"
    DB_PORT: int = 5432
    DB_NAME: str = "nexovant_production"
    DB_USER: str = "nexovant_app"
    DB_PASSWORD: str = "nexovant_secret"

    # Router Safeguards
    ROUTER_RPM_WARNING_THRESHOLD: float = 0.70
    ROUTER_RPM_CRITICAL_THRESHOLD: float = 0.90
    CIRCUIT_BREAKER_MAX_FAILURES: int = 3
    CIRCUIT_BREAKER_COOLDOWN_SECONDS: int = 180

    @property
    def database_url(self) -> str:
        return (
            f"postgresql+asyncpg://{self.DB_USER}:{self.DB_PASSWORD}"
            f"@{self.DB_HOST}:{self.DB_PORT}/{self.DB_NAME}"
        )

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore",
    )


settings = Settings()
