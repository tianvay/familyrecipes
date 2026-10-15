"""
Erzeugt die App-Icons des Rezeptbuchs (Pfanne mit Spiegelei auf Terrakotta).

    python -I resources/icons/generate.py

Ausgabe nach public/: apple-touch-icon.png (180), icon-192.png, icon-512.png, favicon.ico.
Das Motiv liegt innerhalb der inneren 70 %, damit es auch als „maskable“ Icon nicht beschnitten wird.
"""

from pathlib import Path

from PIL import Image, ImageDraw

TERRACOTTA = (200, 85, 61)
PAN = (43, 36, 32)
PAN_RIM = (74, 63, 56)
EGG_WHITE = (251, 247, 241)
YOLK = (245, 183, 61)
YOLK_SHINE = (252, 222, 150)

SCALE = 4  # in 4-facher Größe zeichnen und herunterrechnen = glatte Kanten
BASE = 512
PUBLIC = Path(__file__).resolve().parents[2] / "public"


def draw_icon() -> Image.Image:
    size = BASE * SCALE
    s = lambda v: v * SCALE  # noqa: E731
    image = Image.new("RGB", (size, size), TERRACOTTA)
    d = ImageDraw.Draw(image)

    # Pfannenstiel waagerecht nach rechts (schräg sähe es wie eine Lupe aus)
    d.rounded_rectangle([s(318), s(234), s(448), s(278)], radius=s(22), fill=PAN)

    # Pfanne (Mittelpunkt 210/256, Radius 122)
    d.ellipse([s(88), s(134), s(332), s(378)], fill=PAN_RIM)
    d.ellipse([s(102), s(148), s(318), s(364)], fill=PAN)

    # Eiweiß (aus mehreren Kreisen für eine weiche, unregelmäßige Form)
    for x0, y0, x1, y1 in [(142, 196, 276, 322), (132, 174, 232, 274), (186, 180, 278, 272), (150, 236, 246, 330)]:
        d.ellipse([s(x0), s(y0), s(x1), s(y1)], fill=EGG_WHITE)

    # Eigelb mit Glanzpunkt
    d.ellipse([s(172), s(218), s(244), s(290)], fill=YOLK)
    d.ellipse([s(187), s(231), s(207), s(251)], fill=YOLK_SHINE)

    return image.resize((BASE, BASE), Image.LANCZOS)


def main() -> None:
    icon = draw_icon()

    icon.resize((180, 180), Image.LANCZOS).save(PUBLIC / "apple-touch-icon.png", optimize=True)
    icon.resize((192, 192), Image.LANCZOS).save(PUBLIC / "icon-192.png", optimize=True)
    icon.save(PUBLIC / "icon-512.png", optimize=True)
    icon.save(PUBLIC / "favicon.ico", sizes=[(16, 16), (32, 32), (48, 48)])

    print(f"Icons gespeichert in {PUBLIC}")


if __name__ == "__main__":
    main()
