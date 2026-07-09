# Rohr- & Kanalreinigung — modernes Website-Layout

Ein modernes, vollständig responsives Landingpage-Layout für einen Rohr- &
Kanalreinigungs-Betrieb. Statisches HTML/CSS/JS ohne Build-Schritt und **ohne
externe Runtime-Abhängigkeiten**. Es werden bewusst **keine Google Fonts**
geladen (DSGVO): Die Seite nutzt einen System-Font-Stack und überträgt damit
keine IP-Adressen an Dritte.

## Vorschau starten

Einfach `index.html` im Browser öffnen – oder lokal ausliefern:

```bash
# eine der Varianten
python3 -m http.server 8080
# dann http://localhost:8080 öffnen
```

## Aufbau

```
index.html          # Startseite (Hero, Leistungen, Ablauf, Preise, FAQ, Kontakt …)
Impressum.html      # Pflichtseite (Platzhalter ausfüllen)
Datenschutz.html    # Pflichtseite (an eigene Verarbeitung anpassen)
css/styles.css      # Design-System, Layout, Responsive, Animationen
js/main.js          # Mobile-Nav, Scroll-Reveal, Zähler, FAQ, Formular
assets/             # SVG-Illustrationen & Favicon
```

## Anpassen (Wichtig vor dem Livegang)

Die folgenden Platzhalter sind bewusst neutral gehalten und müssen ersetzt
werden:

| Platzhalter | Bedeutung | Wo |
|---|---|---|
| `0000 000 000` / `tel:+490000000000` | Telefonnummer / Notruf | `index.html`, `Impressum.html`, `Datenschutz.html` |
| `info@rohr-kanalreinigung.com` | E-Mail-Adresse | `index.html`, Footer, Kontakt |
| Preise (89/149/199 €) | Richtpreise | `index.html` → Abschnitt „Preise" |
| Firmenname, Anschrift, USt-ID | Rechtliche Angaben | `Impressum.html` |
| Bewertungstexte | Kundenstimmen | `index.html` → Abschnitt „Bewertungen" |

### Schriftart (optional aufwerten)

Aus Datenschutzgründen lädt die Seite keine externen Fonts. Möchten Sie
Manrope/Inter dennoch nutzen, laden Sie die Dateien **lokal** herunter (z. B.
per *google-webfonts-helper*), legen Sie sie unter `assets/fonts/` ab und
binden Sie sie via `@font-face` in `css/styles.css` ein. Der Font-Stack in
`--font` verwendet sie dann automatisch. So bleibt die Seite DSGVO-konform.

### Farben ändern

Alle Farben liegen als CSS-Variablen ganz oben in `css/styles.css` (`:root`).
Ein Anpassen von `--brand`, `--brand-2` und `--amber` genügt für ein
komplettes Rebranding.

### Bilder / echte Fotos einsetzen

Die Illustrationen unter `assets/` sind selbst erstellte SVGs (laden sofort,
skalieren verlustfrei). Möchten Sie echte Fotos verwenden:

- **Hero:** In `index.html` das `<img src="assets/hero.svg" …>` durch Ihr
  Foto ersetzen (empfohlen ~1200×1100 px, `.webp`/`.jpg`).
- **Warum-wir / Karte:** analog `assets/inspection.svg` bzw. `assets/map.svg`
  ersetzen.

Die Bildcontainer haben feste Radien/Schatten, ein Foto fügt sich also ohne
weitere Änderungen ein.

### Kontaktformular scharf schalten

Das Formular gibt aktuell nur ein clientseitiges Erfolgs-Feedback
(`js/main.js`). Für echten Versand an einen Mail-/Backend-Dienst die
`submit`-Behandlung entsprechend anbinden (z. B. Formspree, eigenes
Endpoint, Netlify Forms).

## Barrierefreiheit & Performance

- Semantisches HTML, `aria`-Attribute, Fokuszustände.
- Respektiert `prefers-reduced-motion`.
- Keine Framework-Abhängigkeiten, sehr schneller Erstaufbau.
