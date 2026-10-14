# Changelog

## 1.5.3

- Neutral author and copyright lines; screenshots use only fictitious names and example.com addresses.
- Login page: the captcha widget (Cloudflare Turnstile / reCAPTCHA) is no longer clipped by a rounded, overflow-hidden frame.
- Conversation: icon, label and caret of the Assign and Status buttons now share one centre line.
- E-mail display switch (original / adapted): it now sits on the top edge of the message frame, clear of the date, and stays visible instead of appearing on hover only.
- "This is a bounce message" notice is shown above the message again (blank text before it used to push the message in front of it).
- Printing always uses the light theme and the original mail display: white paper, black text, no tints, shadows or switch button, to save ink. The conversation print view (?print=1) is light from the start.

## 1.5.2

- Top bar: the user's name is now centred on its capital letters (CSS `text-box`), so it lines up with the avatar and the icons whatever the system font. Browsers without `text-box` support keep the previous behaviour.

## 1.5.1

- Top bar: the theme button, bell, avatar, name and search icon now sit on one centre line (FreeScout's 3 px offset for its own icon font is removed, and the user block has the same height as the icon buttons).
- Updated screenshots, matching a real FreeScout installation.

## 1.5.0 - first public release

- Update from GitHub: native update button in Manage > Modules, plus an updater in Settings > Atelier with automatic backup and rollback.
- New icon and a short module description.
- Per-mailbox accent colours: curated palette, popover pickers, "Auto" and "Reset" buttons, and an option to keep the global accent inside mailboxes.
- Dashboard cards: tinted header and colour dot per mailbox.
- Settings: redesigned colour picker with named colours, live preview that also works on phones.
- The "Block External Images" notice now sits above the message.
- Menu icons are centred in the Settings menu and in mailbox menus.
- Safer behaviour when the theme is deactivated and reactivated: accent colours are re-applied automatically.
- Fixed a 500 error when saving per-mailbox colours.
- Fixed the live preview being blocked by FreeScout's Content-Security-Policy.
- Translations: English, French, Spanish, German, Italian.

Earlier versions (1.0 to 1.4) were private builds.
