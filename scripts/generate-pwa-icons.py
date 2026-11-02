#!/usr/bin/env python3
"""Generate the PharmaGest PWA icons and favicon from the brand colors."""

from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parent.parent
PUBLIC = ROOT / "public"
SCALE = 1024


def draw_icon() -> Image.Image:
    image = Image.new("RGBA", (SCALE, SCALE), (14, 159, 110, 255))
    draw = ImageDraw.Draw(image)

    # A subtle darker inner field keeps the white cross readable on all
    # launchers without adding a dependency on a font.
    margin = 72
    draw.rounded_rectangle(
        (margin, margin, SCALE - margin, SCALE - margin),
        radius=210,
        fill=(12, 43, 33, 255),
    )

    bar_width = 148
    bar_length = 500
    center = SCALE // 2
    draw.rounded_rectangle(
        (center - bar_width // 2, center - bar_length // 2, center + bar_width // 2, center + bar_length // 2),
        radius=bar_width // 2,
        fill=(255, 255, 255, 255),
    )
    draw.rounded_rectangle(
        (center - bar_length // 2, center - bar_width // 2, center + bar_length // 2, center + bar_width // 2),
        radius=bar_width // 2,
        fill=(255, 255, 255, 255),
    )

    return image


def main() -> None:
    icon = draw_icon()

    for size in (192, 512):
        icon.resize((size, size), Image.Resampling.LANCZOS).save(
            PUBLIC / f"pwa-{size}.png", optimize=True
        )

    icon.resize((256, 256), Image.Resampling.LANCZOS).save(
        PUBLIC / "favicon.ico",
        format="ICO",
        sizes=[(16, 16), (32, 32), (48, 48), (64, 64)],
    )


if __name__ == "__main__":
    main()
