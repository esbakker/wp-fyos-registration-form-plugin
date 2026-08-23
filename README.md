# FOYS Registration Form

WordPress plugin that embeds a [FOYS](https://foys.tech) registration form on any page
through a shortcode, and restyles it so it looks like the rest of the site instead of
stock Bootstrap.

```
[fyos_registration_form configuration="085f4107-63a1-44d8-2e07-08dd3cbd1495"]
```

## Installation

1. Download `fyos-registration-form.zip` from the
   [latest release](https://github.com/esbakker/wp-fyos-registration-form-plugin/releases).
2. WordPress admin → **Plugins → Add New → Upload Plugin** → choose the zip → **Install Now** → **Activate**.
3. Optional: set a default form under **Settings → FOYS Registration Form**.

## Shortcode

| Attribute       | Default            | Description |
| --------------- | ------------------ | ----------- |
| `configuration` | setting            | The form GUID supplied by FOYS. Required unless a default is configured. |
| `container`     | setting            | Optional tenant/container key. The form sends it as the `X-Container` header on every FOYS API call. |
| `bootstrap`     | `yes`              | Load the FOYS Bootstrap stylesheet. |
| `theme`         | `yes`              | Apply the WordPress theme styling. |
| `class`         | –                  | Extra CSS class(es) on the wrapper `<div>`. |

Examples:

```
[fyos_registration_form configuration="085f4107-63a1-44d8-2e07-08dd3cbd1495"]
[fyos_registration_form configuration="085f4107-…" container="my-club"]
[fyos_registration_form configuration="085f4107-…" theme="no"]
```

**One form per page.** The FOYS app mounts on the first `<registration-form-entry>`
element it finds in the document; a second one on the same page would stay blank.
The shortcode detects this and shows a notice to logged-in editors.

## What the plugin outputs

```html
<div class="frf-registration-form">
  <registration-form-entry configuration="…"></registration-form-entry>
</div>
```

plus, enqueued through WordPress:

| Asset | Purpose |
| ----- | ------- |
| `https://registration-form.foys.tech/chunk-vendors.css` | Bootstrap 4.6.1 + BootstrapVue, scoped to `.registration-form` |
| `https://registration-form.foys.tech/app.css`           | The form's own styles |
| `assets/css/frf-theme.css` + inline variables            | The WordPress theme bridge |
| `https://registration-form.foys.tech/chunk-vendors.js`  | Vue 2, BootstrapVue, axios, Popper |
| `https://registration-form.foys.tech/app.js`            | The form itself |
| `assets/js/frf-portal.js`                                | Keeps BootstrapVue's body-level overlays inside the CSS scope |

## Notes on the snippet FOYS supplies

The embed snippet in the FOYS documentation needs a few corrections, all of which this
plugin applies:

* **`chuck-vendors.css` is a typo** for `chunk-vendors.css`. The misspelled URL still
  returns HTTP 200, but the response is the SPA's `index.html` served as
  `text/html`, so the browser discards it and the form renders unstyled.
* **jQuery, Popper and `bootstrap.min.js` are not needed.** The bundle is Vue 2 with
  BootstrapVue, which reimplements Bootstrap's behaviour in Vue; `app.js` and
  `chunk-vendors.js` contain no jQuery references at all, and Popper is already bundled
  inside `chunk-vendors.js`.
* **`bootstrap-vue.min.css` from unpkg is not needed either** — and it is unscoped, so it
  *would* leak into the theme. `chunk-vendors.css` already contains the BootstrapVue CSS,
  scoped.
* **`foys-bootstrap.min.css` is redundant** when `chunk-vendors.css` is loaded. Both are
  scoped to `.registration-form`; `chunk-vendors.css` is the superset (Bootstrap +
  BootstrapVue), `foys-bootstrap.min.css` is Bootstrap only.
* The warning about Bootstrap conflicting with the theme no longer applies: every
  selector in both FOYS stylesheets is prefixed with `.registration-form`.

## Attributes on `<registration-form-entry>`

Read out of the published `app.js` bundle, these are the only two attributes the app
looks at:

| Attribute       | Required | Effect |
| --------------- | -------- | ------ |
| `configuration` | yes      | GUID of the form configuration. Fetched from `https://api.foys.io/foys/api/v2/pub/registration-forms/{guid}`. The app throws if it is missing. |
| `container`     | no       | Value sent as the `X-Container` request header on every call to the FOYS API. |

Everything else is server-side configuration returned by that endpoint: locale
(`en-GB`, `nl`, `fr`, `de`), the field groups and their validation, reCAPTCHA site key,
terms and privacy links, parent-info, file uploads, address lookup, vouchers and
payment options. Change those in FOYS, not in the embed.

## Styling

`assets/css/frf-theme.css` maps the form onto the theme:

* fonts, font size, line height and text colour are inherited from the theme;
* buttons, links, focus rings, checkboxes, radios and the date picker use one accent
  colour;
* inputs and buttons use one corner radius.

The accent colour is resolved server-side, in this order:

1. the **Accent colour** setting, if filled in;
2. `accent_color`, `primary_color` or `link_color` theme mods (classic themes);
3. the `primary`, `accent` or `link` colour from `theme.json` (block themes);
4. a `var(--wp--preset--color--primary, …)` chain resolved in the browser, falling back
   to Bootstrap blue.

The accent text colour is picked automatically from the accent's relative luminance
unless it is set explicitly.

Anything left over goes in the **Extra CSS** box on the settings screen. Selectors there
are scoped to the form for you, so `.btn { … }` becomes
`.registration-form .btn { … }` and cannot reach the rest of the site:

| You write | The page gets |
| --- | --- |
| `.btn { color: red }` | `.registration-form .btn { color: red }` |
| `a, p span { … }` | `.registration-form a, .registration-form p span { … }` |
| `body { … }` / `:root { … }` | `.registration-form { … }` |
| `@media (…) { .btn { … } }` | the inner rule is scoped, the query is kept |
| `.registration-form .btn { … }` | unchanged — already scoped |

`@keyframes` and `@font-face` are passed through untouched, since their contents are not
selectors.

### Filters

```php
// Serve the FOYS bundle from a different host.
add_filter( 'frf_base_url', fn() => 'https://registration-form.foys.tech' );

// Adjust the generated custom properties.
add_filter( 'frf_theme_css', fn( $css, $vars ) => $css . '.registration-form .btn{text-transform:uppercase}', 10, 2 );

// Change the rendered markup.
add_filter( 'frf_shortcode_html', fn( $html, $atts ) => $html, 10, 2 );
```

## Building

```bash
bash build-zip.sh
```

```powershell
.\build-zip.ps1
```

Both produce `fyos-registration-form.zip` with the plugin files at the archive root,
ready for **Plugins → Add New → Upload Plugin**.

### Releasing

The version in the plugin header is the source of truth. Bump it as part of your change:

```bash
bash bump.sh patch      # or minor, major, or an explicit 2.1.0
```

```powershell
.\bump.ps1 patch
```

Pushing that commit to `main` runs `.github/workflows/release.yml`, which reads the
version, builds the zip, and publishes a GitHub release with the zip attached. The tag is
created by the release API at the pushed commit — the workflow never writes to the
repository, so a ruleset protecting `main` does not block it. A push whose version is
already released does nothing, so ordinary commits without a bump are free.

## Requirements

WordPress 5.6+, PHP 7.2+. The form is loaded from `registration-form.foys.tech` and
talks to `api.foys.io`, so visitors need to be able to reach both.
