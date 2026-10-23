"""Generate the Record PDF art set (SVG) in the shared amjadiqbal template.

Usage: python3 -I make_art.py   (writes SVGs next to this file; render.sh makes the PNGs)
No brown: the only accent is sky blue; no warm colours anywhere.
"""
import os

HERE = os.path.dirname(os.path.abspath(__file__))
FONT = "Helvetica, Arial, sans-serif"
PKG = "amjadiqbal/filament-record-pdf"
TITLE = "Record PDF"
TAGLINE = "Preview, download, email and save any record as a PDF"
CHIPS = ["Blade view per resource", "bulk ZIP · email · save", "Filament 4 · 5"]

THEMES = {
    "light": dict(bg=("#F8FAFC", "#E0F2FE"), title="#0F172A", accent="#0369A1", chip_fill="#FFFFFF",
                  chip_stroke="#E2E8F0", chip_text="#334155", card="#FFFFFF", card_stroke="#E2E8F0",
                  muted="#64748B", body="#0F172A", head_fill="#E0F2FE", head_text="#075985", pkg="#94A3B8",
                  ok="#0284C7"),
    "dark": dict(bg=("#0B1220", "#0B2235"), title="#F8FAFC", accent="#7DD3FC", chip_fill="#111C33",
                 chip_stroke="#273449", chip_text="#CBD5E1", card="#111C33", card_stroke="#273449",
                 muted="#94A3B8", body="#F8FAFC", head_fill="#0C2A43", head_text="#7DD3FC", pkg="#64748B",
                 ok="#38BDF8"),
}


def icon_body():
    """The 512x512 mark: a page with a folded corner and a PDF band, with a download badge."""
    return """
    <rect width="512" height="512" rx="112" fill="url(#iconbg)"/>
    <!-- page -->
    <path d="M118 84 H296 L380 168 V404 Q380 428 356 428 H118 Q94 428 94 404 V108 Q94 84 118 84 Z" fill="#F8FAFC"/>
    <path d="M296 84 V144 Q296 168 320 168 H380 Z" fill="#BAE6FD"/>
    <!-- record lines -->
    <rect x="128" y="122" width="120" height="18" rx="9" fill="#0F172A"/>
    <rect x="128" y="160" width="84" height="14" rx="7" fill="#94A3B8"/>
    <rect x="128" y="196" width="220" height="12" rx="6" fill="#CBD5E1"/>
    <rect x="128" y="222" width="190" height="12" rx="6" fill="#CBD5E1"/>
    <rect x="128" y="248" width="206" height="12" rx="6" fill="#CBD5E1"/>
    <!-- PDF band -->
    <rect x="94" y="290" width="200" height="72" rx="14" fill="#0284C7"/>
    <text x="194" y="341" text-anchor="middle" font-family="Helvetica, Arial, sans-serif" font-size="44" font-weight="800" fill="#FFFFFF" letter-spacing="2">PDF</text>
    <!-- download badge -->
    <circle cx="380" cy="380" r="84" fill="#0EA5E9" stroke="#0F172A" stroke-width="14"/>
    <g fill="none" stroke="#FFFFFF" stroke-width="16" stroke-linecap="round" stroke-linejoin="round">
      <path d="M380 330 V400"/><path d="M350 372 L380 402 L410 372"/><path d="M338 420 H422"/>
    </g>
"""


def defs(t):
    return f"""  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{t['bg'][0]}"/>
      <stop offset="100%" stop-color="{t['bg'][1]}"/>
    </linearGradient>
    <linearGradient id="iconbg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0F172A"/>
      <stop offset="100%" stop-color="#0C4A6E"/>
    </linearGradient>
  </defs>"""


def chips(t, x, y, size=15, h=32, gap=10, anchor_center=False):
    widths = [round(len(c) * size * 0.56 + 34) for c in CHIPS]
    if anchor_center:
        x = x - (sum(widths) + gap * (len(widths) - 1)) / 2
    out = [f'  <g font-family="{FONT}" font-size="{size}" font-weight="600" fill="{t["chip_text"]}" text-anchor="middle">']
    for c, w in zip(CHIPS, widths):
        out.append(f'<g transform="translate({x:.0f},{y})"><rect width="{w}" height="{h}" rx="{h/2}" fill="{t["chip_fill"]}" stroke="{t["chip_stroke"]}"/>'
                   f'<text x="{w/2}" y="{h*0.66:.2f}">{c}</text></g>')
        x += w + gap
    out.append("  </g>")
    return "\n".join(out)


ITEMS = [("Preview", "eye"), ("Download PDF", "down"), ("Email PDF", "mail"), ("Save to record", "save")]
ICONS = {
    "eye": '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    "down": '<path d="M12 4v11"/><path d="M7 10l5 5 5-5"/><path d="M5 20h14"/>',
    "mail": '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
    "save": '<path d="M5 3h11l3 3v15H5z"/><path d="M8 3v6h8V3"/><path d="M8 21v-7h8v7"/>',
}


def menu_card(t, x, y, s=1.0):
    """A miniature row-actions dropdown: the record name, then the plugin's actions."""
    h = 70 + 40 * len(ITEMS)
    out = [f'  <g transform="translate({x},{y}) scale({s})" font-family="{FONT}">',
           f'    <rect width="300" height="{h}" rx="16" fill="{t["card"]}" stroke="{t["card_stroke"]}" stroke-width="1.5"/>',
           f'    <rect x="16" y="16" width="268" height="38" rx="10" fill="{t["head_fill"]}"/>',
           f'    <text x="30" y="40" font-size="13" font-weight="700" fill="{t["head_text"]}">Invoice INV-1042</text>',
           f'    <text x="270" y="40" font-size="12" font-weight="600" fill="{t["muted"]}" text-anchor="end">A4 · dompdf</text>']
    for i, (label, ic) in enumerate(ITEMS):
        ry = 66 + i * 40
        out.append(f'    <line x1="0" y1="{ry}" x2="300" y2="{ry}" stroke="{t["card_stroke"]}"/>')
        out.append(f'    <g transform="translate(20 {ry+9}) scale(0.9)" fill="none" stroke="{t["ok"]}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{ICONS[ic]}</g>')
        out.append(f'    <text x="52" y="{ry+25}" font-size="14" font-weight="600" fill="{t["body"]}">{label}</text>')
    out.append("  </g>")
    return "\n".join(out)


def svg(w, h, body, t):
    return f'<svg width="{w}" height="{h}" viewBox="0 0 {w} {h}" xmlns="http://www.w3.org/2000/svg">\n{defs(t)}\n  <rect width="{w}" height="{h}" fill="url(#bg)"/>\n{body}\n</svg>\n'


def banner(name):
    t = THEMES[name]
    body = f"""  <g transform="translate(60,75) scale(0.29297)">{icon_body()}</g>
  <text x="235" y="148" font-family="{FONT}" font-size="64" font-weight="700" fill="{t['title']}">{TITLE}</text>
  <text x="238" y="190" font-family="{FONT}" font-size="22" font-weight="500" fill="{t['accent']}" letter-spacing="0.3">{TAGLINE}</text>
{chips(t, 238, 218)}
{menu_card(t, 920, 34, s=0.78)}
  <text x="1170" y="284" text-anchor="end" font-family="{FONT}" font-size="15" fill="{t['pkg']}">{PKG}</text>"""
    return svg(1200, 300, body, t)


def social():
    t = THEMES["dark"]
    body = f"""  <g transform="translate(490,50) scale(0.5859)">{icon_body()}</g>
  <text x="640" y="430" text-anchor="middle" font-family="{FONT}" font-size="76" font-weight="700" fill="{t['title']}">{TITLE}</text>
  <text x="640" y="482" text-anchor="middle" font-family="{FONT}" font-size="28" font-weight="500" fill="{t['accent']}">{TAGLINE}</text>
{chips(t, 640, 530, size=18, h=40, gap=14, anchor_center=True)}
  <text x="1240" y="610" text-anchor="end" font-family="{FONT}" font-size="18" fill="{t['pkg']}">{PKG}</text>"""
    return svg(1280, 640, body, t)


def directory():
    """16:9 LIGHT listing image for filamentphp.com: name and tagline beside a real crop of the preview modal
    (screenshots/preview-modal-light.png, taken from the L13/F5 test panel). Authored at 1280x720, rendered 2x."""
    t = THEMES["light"]
    body = f"""  <g transform="translate(70,90) scale(0.21)">{icon_body()}</g>
  <text x="70" y="270" font-family="{FONT}" font-size="58" font-weight="700" fill="{t['title']}">{TITLE}</text>
  <text x="72" y="312" font-family="{FONT}" font-size="21" font-weight="500" fill="{t['accent']}">Any Filament record as a PDF</text>
  <g font-family="{FONT}" font-size="18" fill="{t['chip_text']}">
    <text x="72" y="380">• Preview in a modal, download, email</text>
    <text x="72" y="414">• Save to a disk, a column or media</text>
    <text x="72" y="448">• Bulk download as a ZIP, queued</text>
    <text x="72" y="482">• Your Blade view, dompdf by default</text>
    <text x="72" y="516">• Policies per record · Filament 4 · 5</text>
  </g>
  <rect x="553" y="53" width="694" height="606" rx="14" fill="#FFFFFF" stroke="#E2E8F0"/>
  <clipPath id="shot"><rect x="562" y="61" width="676" height="590" rx="12"/></clipPath>
  <image href="screenshots/preview-modal-light.png" x="556" y="56" width="688" height="600" preserveAspectRatio="xMidYMid meet" clip-path="url(#shot)"/>
  <text x="72" y="680" font-family="{FONT}" font-size="15" fill="{t['pkg']}">{PKG}</text>"""
    return svg(1280, 720, body, t)


def icon():
    return f'<svg width="512" height="512" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg">\n{defs(THEMES["dark"])}\n<g>{icon_body()}</g>\n</svg>\n'


if __name__ == "__main__":
    files = {"icon.svg": icon(), "banner-light.svg": banner("light"), "banner-dark.svg": banner("dark"),
             "social-preview.svg": social(), "filament-directory.svg": directory()}
    for f, s in files.items():
        with open(os.path.join(HERE, f), "w") as fh:
            fh.write(s)
        print("wrote", f)
