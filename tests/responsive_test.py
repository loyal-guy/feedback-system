"""
Responsive / mobile test for FeedbackHub (optional - needs Python + Playwright).

    pip install playwright && playwright install chromium
    python tests/responsive_test.py http://localhost/feedback-system

For each page and screen size it checks:
  * the page has no horizontal scrolling (content fits the screen)
  * the menu button is shown on phones and hidden on computers
  * the mobile menu opens when tapped
and saves screenshots to assets/img/screenshots/ plus results to tests/responsive.json.
"""
import json
import sys
import time
from pathlib import Path

from playwright.sync_api import sync_playwright

BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://localhost/feedback-system").rstrip("/")
ROOT = Path(__file__).resolve().parent.parent
SHOTS = ROOT / "assets" / "img" / "screenshots"
SHOTS.mkdir(parents=True, exist_ok=True)

DEVICES = {
    "Phone (375x812)": (375, 812),
    "Tablet (768x1024)": (768, 1024),
    "Laptop (1366x768)": (1366, 768),
}
USER_PAGES = [("Home", "index.php"), ("Login", "login.php"), ("Register", "register.php"),
              ("Give feedback", "feedback_new.php"), ("My feedback", "my_feedback.php"), ("Profile", "profile.php")]
ADMIN_PAGES = [("Admin dashboard", "admin/index.php"), ("All feedback", "admin/feedback.php")]


def login(page, email, password):
    page.goto(f"{BASE}/login.php")
    page.fill("#email", email)
    page.fill("#password", password)
    page.click("button[type=submit]")
    page.wait_for_load_state("networkidle")


results = []
with sync_playwright() as p:
    browser = p.chromium.launch()
    for device, (w, h) in DEVICES.items():
        for role, pages, creds in [("user", USER_PAGES, ("demo@feedbackhub.test", "User@1234")),
                                   ("admin", ADMIN_PAGES, ("admin@feedbackhub.test", "Admin@123"))]:
            ctx = browser.new_context(viewport={"width": w, "height": h})
            page = ctx.new_page()
            logged_in = False
            for name, path in pages:
                if path not in ("index.php", "login.php", "register.php") and not logged_in:
                    login(page, *creds)
                    logged_in = True
                page.goto(f"{BASE}/{path}")
                page.wait_for_load_state("networkidle")
                overflow = page.evaluate("document.documentElement.scrollWidth > window.innerWidth + 1")
                toggle_visible = page.is_visible(".nav-toggle")
                menu_ok = True
                if w < 900:
                    page.click(".nav-toggle")
                    menu_ok = page.is_visible("#main-nav")
                    page.click(".nav-toggle")
                nav_ok = toggle_visible == (w < 900)
                passed = (not overflow) and nav_ok and menu_ok
                shot = f"{path.replace('/', '_').replace('.php', '')}_{w}.png"
                page.screenshot(path=str(SHOTS / shot), full_page=False)
                actual = []
                actual.append("no horizontal scroll" if not overflow else "HORIZONTAL SCROLL")
                actual.append(("menu button shown" if w < 900 else "full menu shown") if nav_ok else "wrong menu type")
                if w < 900:
                    actual.append("menu opens" if menu_ok else "menu does not open")
                results.append({"device": device, "page": name, "pass": passed,
                                "actual": ", ".join(actual), "screenshot": shot})
                print(f"  {'PASS' if passed else 'FAIL'}  {device:18} {name}")
            ctx.close()
    browser.close()

out = {"run_at": time.strftime("%Y-%m-%d %H:%M:%S"), "target": BASE, "results": results,
       "passed": sum(r["pass"] for r in results), "total": len(results)}
(ROOT / "tests" / "responsive.json").write_text(json.dumps(out, indent=2))
print(f"\n{out['passed']} / {out['total']} responsive checks passed. Saved tests/responsive.json")
