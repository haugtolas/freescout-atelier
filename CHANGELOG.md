# Changelog

## 1.7.1

- Liquid glass: the lit edge of cards, mailbox cards and dialogs now runs all the way round. It used to fade out completely at the top right and bottom left corners, so those corners looked dull, almost angular, next to the bright top left one. The edge keeps its light-from-the-top-left feel but never disappears, in light and dark.
- Home page: the settings menu of a mailbox card no longer slips under the card below it. A card that is hovered, or whose menu is open, now rises above its neighbours, so every entry of the menu stays visible and clickable (the menu used to vanish behind the next card as soon as the pointer came near).
- Icon-only buttons are centred exactly, in light and dark, with or without Liquid glass, with Atelier's icons or the original ones: the arrow that opens a mailbox, the envelope and the cog with its small arrow in the mailbox buttons, and the other icon buttons of the interface. The glyph and the arrow of the cog sit on the same line, and the pair is centred in the button.

## 1.7.0

- New, optional: **Liquid glass**. Translucent, blurred surfaces (top bar, cards, dialogs) over a soft background in your accent colour that reaches the whole page, in light and dark. Menus and pop-overs are almost opaque so text behind them never shows through; the glass look comes from their lit edge, highlight and shadow. The top bar floats and stays in view. Never applied to e-mails, conversation threads or lists. Off by default: choose it in Settings > Atelier > Surfaces, and each agent can switch it from the theme menu in the top bar. It switches itself off when the device asks for reduced transparency or more contrast, and on browsers without background blur.
- Rounded corners done properly across the whole interface: every shape that sits inside a rounded one uses "outer radius minus gap" (concentric corners). Mailbox cards, panels, wells, the conversation sidebar blocks (header follows the corners, all four corners when collapsed), menu items, pop-over titles, dialog headers and footers, the editor frame and toolbar, and the button row of a card. Menus opened from the top bar float 6 px under it so their four corners are free.
- Buttons and fields follow one size scale, with the corner radius growing with the height: extra small 24 px, small 32 px, normal 36 px, large 44 px. Drop-down lists look the same in every browser (they were small pills on iPhone and Safari). Pagination, pills, input groups, labels and counters follow the same radii. Primary buttons get a smooth sheen with Liquid glass.
- Mailbox cards: the coloured band runs to the edge of the card; the counter at the end of a row (new conversations) stays on the line, pinned to the right, even with a long "waiting since" (which is shortened with an ellipsis); the digits line up with or without the grey pill.
- Menus of a mailbox card and of the mailbox sidebar buttons open upwards or downwards depending on the room, and scroll if the window is too short (the top row used to run under the top bar).
- Notifications menu: header, dates and rows run edge to edge and follow the rounded corners of the menu.
- Mailbox sidebar: the active folder only shows its rounded highlight, without a flat rectangle behind it.
- Editors (reply box, templates such as the satisfaction text, signatures, auto-reply): HTML written for a white page, with dark text set inline, was unreadable on the dark theme. It is adapted for display exactly like received e-mails (or shown on white in "paper" mode). Dark logos and pictures sit on a light backing and keep their colours. The HTML itself is never modified.
- A default changed in Settings > Atelier (theme, e-mail display, palette, density, Liquid glass) now applies to everyone once. Before, an agent who had ever picked a value from the top-bar menu kept it for ever. After that one-time reset each agent can still choose for themselves.
- Settings: the theme preview thumbnails use matching radii.

## 1.6.0

- New: quiet announcements for administrators. A small card in the corner tells you about a new feature or asks for your opinion. Shown once per announcement and per administrator, at most one every few days, never on a conversation, never on a phone, plain text only. Once a day the server downloads one public file (`news.json`) from this repository; nothing about your helpdesk is sent. On by default, and one click to turn off in Settings > Atelier > Announcements (then nothing is downloaded or shown). A link in an announcement can only point to this project on GitHub or to a survey service, and the destination is always shown. The latest announcements stay readable in the settings.

## 1.5.8 - first public release

- Teams (and any card with a round icon): the glyph is centred exactly in its disc.
- Reports: date fields keep their value inside the box. On iPhone the value sat lower than its box and spilled over the border.

Earlier versions (1.0 to 1.5.7) were private builds.
