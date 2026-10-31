import os
import requests
from dotenv import load_dotenv

load_dotenv()

class WordPressBridge:
    def __init__(self):
        self.site_url = os.getenv("WP_SITE_URL", "https://steamgears.eu")
        self.user = os.getenv("WP_USER")
        self.password = os.getenv("WP_APP_PASSWORD")
        self.auth = (self.user, self.password) if self.user and self.password else None

    def check_health(self) -> bool:
        try:
            res = requests.get(f"{self.site_url.rstrip('/')}/wp-json/wp/v2/posts", auth=self.auth, timeout=10)
            return res.status_code == 200
        except Exception:
            return False
