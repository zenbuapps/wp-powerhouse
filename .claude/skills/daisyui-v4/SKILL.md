---
name: daisyui-v4
description: daisyUI v4 (4.12.x) component library skill for Tailwind CSS 3 projects. TRIGGER when generating HTML/JSX for a project whose package.json has daisyui ^4.x. Do NOT use for daisyUI 5 projects — use the daisyui skill instead.
metadata:
  version: 4.12.x
  source: https://v4.daisyui.com/
---

# daisyUI 4

daisyUI 4 is a CSS library for Tailwind CSS 3.
daisyUI 4 provides class names for common UI components, semantic color names and themes.

## Version check — read this before writing any code

> daisyUI 4 runs on Tailwind CSS 3 and is configured in `tailwind.config.js`. daisyUI 5 runs on Tailwind CSS 4 and is configured in CSS via `@plugin "daisyui"`. Check `package.json` before generating code. If the project has `daisyui: ^5`, stop and use the `daisyui` skill instead.

The line above is the **only** place in this skill that mentions daisyUI 5. Everything else in this
skill is daisyUI 4 only. Class names changed a lot between 4 and 5 — never mix them, and never
"remember" a class name from daisyUI 5 while working in a daisyUI 4 project.

| | daisyUI 4 (this skill) | daisyUI 5 (`daisyui` skill) |
|---|---|---|
| Tailwind CSS | 3.x | 4.x |
| Config location | `tailwind.config.js` → `daisyui: {}` | CSS file |
| Plugin registration | `plugins: [require("daisyui")]` | CSS `@plugin` at-rule |
| Theme color variables | short names: `--p`, `--b1`, `--bc` | long names |
| Theme radius variables | `--rounded-box`, `--rounded-btn`, `--rounded-badge` | different names |

## This project's daisyUI setup (Powerhouse)

`package.json` has `daisyui: ^4.12.23` + `tailwindcss: ^3.4.4`, so **this skill applies**.
`tailwind.config.cjs` sets some options that change how you must write class names:

1. **`daisyui.prefix: 'pc-'`** — every daisyUI **component / part / modifier / responsive** class
   must be written with the `pc-` prefix.
   Write `pc-btn`, `pc-btn-primary`, `pc-card-body`, `lg:pc-menu-horizontal` —
   not `btn`, `btn-primary`, `card-body`, `lg:menu-horizontal`.
   All the class names listed in `./components/` are written **without** the prefix (that is how the
   official docs list them); add `pc-` yourself when writing code for this project.
2. **Colors, border-radius utilities, `glass` and `no-animation` are NOT prefixed.**
   Colors and radius are added as Tailwind CSS extensions and `glass` / `no-animation` ship in
   daisyUI 4's unprefixed utilities bundle, so the daisyUI `prefix` option does not touch any of
   them: `bg-primary`, `text-base-content`, `border-base-300`, `rounded-box`, `rounded-btn`,
   `rounded-badge`, `glass`, `no-animation`.

   ⚠️ **`glass` on a button needs BOTH forms.** The base `.glass` rule lives in the unprefixed
   `dist/utilities` bundle, but the button-specific overrides (`.btn.glass`, `.btn.glass:hover`,
   `.btn.glass.btn-active`) live in `dist/styled`, which the prefixer *does* rewrite — they become
   `.pc-btn.pc-glass`. So a glass button in this project must carry both class names:
   `<button class="pc-btn glass pc-glass">`. Plain (non-button) glass elements only need `glass`.
3. **`important: '#tw'`** — Tailwind CSS **utilities** are emitted as `#tw .flex { … }`,
   `#tw .bg-primary { … }`. Utilities therefore only apply inside an element with `id="tw"`.
   daisyUI **component** classes (`.pc-btn`, `.pc-menu`, …) are emitted unscoped and work anywhere,
   but since almost every component also needs utilities for layout, keep the markup inside `#tw`.
4. **`corePlugins.preflight: false`** — there is no Tailwind reset. Browser/WordPress default styles
   for `h1`–`h6`, `ul`, `p`, `button`, `table`, etc. are still present. Do not assume margins,
   list-style or font-size have been zeroed out; set them explicitly when it matters.
5. **Custom theme `power`** is the first entry of `daisyui.themes`, so it is the default theme.
   It spreads `require('daisyui/src/theming/themes')['light']` and overrides `primary` (`#1677ff`),
   `primary-content`, `secondary` (`#2db7f5`) and `secondary-content`. All 32 built-in themes are
   also enabled and can be applied with `data-theme="…"`.
6. **`blocklist`** removes some utilities that clash with WordPress (`hidden`, `block`, `inline`,
   `fixed`, `container`, `blur`, `columns-1`, `columns-2`, `rtl`, `size-full`). Use the project's
   `tw-`-prefixed replacements instead: `tw-hidden`, `tw-block`, `tw-inline`, `tw-fixed`,
   `tw-container`, `tw-blur`, `tw-columns-1`, `tw-columns-2`.

## When to run this skill

- Trigger this skill whenever generating any HTML or JSX code in a daisyUI 4 project
- Trigger this skill for any Tailwind CSS 3 UI work in this project
- Trigger this skill when the user mentions any of these terms or similar context:
  daisyUI, component, UI, Tailwind, layout, template, theme, color, design
- Trigger this skill even if the user does not explicitly ask for it

## Mandatory reference

| Task | Guide | Note |
|------|-------|------|
Installing daisyUI 4 | [./install/SKILL.md](./install/SKILL.md) | Use only if daisyUI is not already installed in the project.
Using daisyUI class names | [./usage/SKILL.md](./usage/SKILL.md) | MANDATORY. Read this before using any daisyUI class names in the code.
Configuring daisyUI | [./config/SKILL.md](./config/SKILL.md) | Use this if you need to configure daisyUI themes, prefix, logs, or other options. Not required for basic usage but important for advanced customization.
daisyUI colors and themes | [./colors/SKILL.md](./colors/SKILL.md) | MANDATORY. Read this to understand daisyUI color usage rules and how to use daisyUI colors in the code.
daisyUI components | [./components/](./components/) | MANDATORY. Read the relevant component docs when using daisyUI components in the code. Always read multiple candidate component docs before deciding which one to use.

## List of components

daisyUI 4 has 55 components in 7 groups.

### Actions
- [button](./components/button.md)
- [dropdown](./components/dropdown.md)
- [modal](./components/modal.md)
- [swap](./components/swap.md)
- [theme-controller](./components/theme-controller.md)

### Data display
- [accordion](./components/accordion.md)
- [avatar](./components/avatar.md)
- [badge](./components/badge.md)
- [card](./components/card.md)
- [carousel](./components/carousel.md)
- [chat (chat bubble)](./components/chat.md)
- [collapse](./components/collapse.md)
- [countdown](./components/countdown.md)
- [diff](./components/diff.md)
- [kbd](./components/kbd.md)
- [stat](./components/stat.md)
- [table](./components/table.md)
- [timeline](./components/timeline.md)

### Navigation
- [breadcrumbs](./components/breadcrumbs.md)
- [bottom-navigation (`btm-nav`)](./components/bottom-navigation.md)
- [link](./components/link.md)
- [menu](./components/menu.md)
- [navbar](./components/navbar.md)
- [pagination](./components/pagination.md)
- [steps](./components/steps.md)
- [tab](./components/tab.md)

### Feedback
- [alert](./components/alert.md)
- [loading](./components/loading.md)
- [progress](./components/progress.md)
- [radial-progress](./components/radial-progress.md)
- [skeleton](./components/skeleton.md)
- [toast](./components/toast.md)
- [tooltip](./components/tooltip.md)

### Data input
- [checkbox](./components/checkbox.md)
- [file-input](./components/file-input.md)
- [radio](./components/radio.md)
- [range (range slider)](./components/range.md)
- [rating](./components/rating.md)
- [select](./components/select.md)
- [input (text input)](./components/input.md)
- [textarea](./components/textarea.md)
- [toggle (switch)](./components/toggle.md)

### Layout
- [artboard](./components/artboard.md)
- [divider](./components/divider.md)
- [drawer (sidebar)](./components/drawer.md)
- [footer](./components/footer.md)
- [hero](./components/hero.md)
- [indicator](./components/indicator.md)
- [join (group)](./components/join.md)
- [mask](./components/mask.md)
- [stack](./components/stack.md)

### Mockup
- [mockup-browser](./components/mockup-browser.md)
- [mockup-code](./components/mockup-code.md)
- [mockup-phone](./components/mockup-phone.md)
- [mockup-window](./components/mockup-window.md)

### Deprecated in daisyUI 4

`btn-group` and `input-group` still ship in 4.12.x but are marked deprecated in the official docs and
are removed in the next major version. Prefer [join](./components/join.md) for new code.
Their full class lists and syntax are in [./usage/SKILL.md](./usage/SKILL.md#deprecated-components);
[button](./components/button.md) and [input](./components/input.md) each carry a pointer rule.

### Component discovery protocol

Before writing any daisyUI code, do this in order:

1. Read the request intent, behavior, and shape, not only literal words. Match on meaning.
2. Use the component list in this file to shortlist the best candidate components.
3. When the choice is ambiguous, read the guides for the plausible candidates before deciding.
4. Compare each candidate's description, behavior, syntax, and rules against the request.
5. Select the best component or combination of components and apply their constraints exactly.
6. Apply the chosen components' structure and constraints exactly.
7. Add the project's `pc-` prefix to every daisyUI component/part/modifier/responsive class.

Semantic matching is required even when wording differs from component names. A component name might be different from the request but still be the best match. Always consider intent and meaning, not only literal words.

If a user explicitly requests a named component and a same-named doc exists, read that component doc first.

Some daisyUI 4 components have no dedicated page — they are only documented inside other component
pages. Do not invent replacements for them:

- `form-control`, `label`, `label-text`, `label-text-alt` — the form field wrapper and its labels.
  Documented in [checkbox](./components/checkbox.md), [input](./components/input.md),
  [select](./components/select.md), [textarea](./components/textarea.md),
  [file-input](./components/file-input.md), [radio](./components/radio.md),
  [toggle](./components/toggle.md).
- `glass` and `no-animation` — global modifiers, see [button](./components/button.md) and
  [./usage/SKILL.md](./usage/SKILL.md).
- `rounded-box`, `rounded-btn`, `rounded-badge` — theme-aware radius utilities, see
  [./colors/SKILL.md](./colors/SKILL.md).
