=== NAVAI Voice ===
Contributors: luxisoft
Tags: voice, ai, assistant, openai, accessibility
Requires at least: 6.2
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 0.3.45
License: MIT
License URI: https://opensource.org/license/mit/

Add a realtime AI voice assistant to WordPress with route control, role-based access, shortcodes, and optional safety workflows.

== Description ==

NAVAI Voice adds a realtime AI voice assistant to WordPress using OpenAI Realtime. It can render as a floating widget or via shortcode, and includes an admin dashboard to manage navigation routes, custom functions, approvals, traces, and session history.

Core features:

* Realtime voice widget for WordPress.
* Floating widget or shortcode rendering.
* Role-based visibility controls.
* Allowed-route navigation catalog for the assistant.
* Custom JavaScript functions managed by administrators.
* Guardrails, approvals, traces, and optional session history.
* Optional MCP integrations for administrator-configured remote tools.

= External services =

This plugin connects to third-party services.

1. OpenAI Realtime

* Service provider: OpenAI
* Service URL: https://api.openai.com/
* Purpose: create realtime voice sessions and process audio/text interactions.
* Data sent: audio captured after the user starts a session, text prompts, selected model/voice/instructions, and related tool payloads needed for the active interaction.
* When it is sent: when a visitor or authenticated user starts a voice/text interaction, and when an administrator tests or configures the voice assistant.
* Privacy policy: https://openai.com/policies/privacy-policy/
* Terms of use: https://openai.com/policies/terms-of-use/

2. Optional MCP servers configured by the site owner

* Service provider: determined by the site administrator.
* Purpose: call remote tools outside WordPress through MCP.
* Data sent: tool arguments, payloads, and request metadata required by the configured remote tool.
* When it is sent: only if an administrator enables MCP, configures a server, and a request triggers one of those remote tools.
* Privacy policy and terms: depend on the external provider selected by the site administrator.

= Privacy =

NAVAI Voice can store session history, transcripts, traces, and approvals inside WordPress when those features are enabled by the site administrator.

By default on new installs:

* Public backend function execution is disabled.
* Session history is disabled.
* Runtime tracing is disabled.
* MCP integrations are disabled until configured by an administrator.

The plugin registers privacy policy guidance and personal data exporter/eraser support for data associated with logged-in WordPress users.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/navai-voice/`, or install it through the WordPress plugin screen.
2. Activate the plugin through the `Plugins` screen in WordPress.
3. Open `NAVAI Voice` from the WordPress admin menu.
4. Add your OpenAI API key and configure the widget.
5. If you need backend tools for public visitors, explicitly enable public backend functions in the plugin settings.

== FAQ ==

= Does this plugin require an external service? =

Yes. OpenAI Realtime is required for voice interactions. Optional MCP integrations connect to additional third-party services configured by the site owner.

= Does the plugin store personal data? =

It can. If session history, approvals, or traces are enabled, the plugin may store conversation text, tool payloads, trace events, and related metadata in WordPress.

= Are backend functions public by default? =

No. Public backend functions are disabled by default on new installs and must be explicitly enabled by an administrator.

= Can I use the plugin without a floating widget? =

Yes. You can render the assistant only through the `[navai_voice]` shortcode.

== Changelog ==

= 0.3.45 =
* Prefixed file-scope variables in admin views and bootstrap files to satisfy Plugin Check naming rules.
* Bumped the packaged plugin version for the review-ready ZIP.

= 0.3.44 =
* Added a WordPress.org-compatible readme.
* Added explicit third-party service and privacy disclosures.
* Added privacy policy content and personal data exporter/eraser integration.
* Switched new-install defaults to safer privacy and access settings.
* Removed remote Google Fonts from the admin UI.
* Stopped hiding unrelated admin notices on the plugin screen.
