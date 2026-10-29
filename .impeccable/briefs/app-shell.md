# Surface brief: app shell and core screens

Scope: the authenticated app shell (navigation, top bar, theme) and the screens it frames (Tiempo, Calendario, Hoja de horas, Panel, Informes). Mode: Operate.

Audience and job: the ~20 VALO members log hours at a laptop many times a day; the admin reads weekly distribution. Constraint: structure familiar to Clockify users; profile, settings and notifications live top-right; dark by default with a light toggle; Spanish UI; money hidden.

Chosen direction: the category standard (user picked canon), quality bar Toggl Track's craft, but more sober and professional, with little colour.

## Direction contract

THESIS: A modern Clockify executed with care: the familiar time-tracker shell, made quieter, denser and more exact. It refuses the AI-default look of glowing accents, pill-everything, gradients and decorative cards.

OWN-WORLD: Graphite neutrals tinted very slightly cool, near-black ground in dark and near-white in light; one restrained blue for primary actions, selection and the running timer only; project colours are the only other colour. Hairline borders, 6px radii, no shadows on resting surfaces, tabular numerals for every duration.

STORY: The member opens the app, sees the timer bar and this week's entries grouped by day with totals, starts or adds time in one click, and leaves. The admin opens Informes and reads the week by member.

FIRST VIEWPORT: Left rail (230px) with grouped sections SEGUIMIENTO, ANÁLISIS, GESTIONAR; top bar across the content with page title left and, on the right, help, notifications, settings, theme toggle and avatar menu (perfil, ajustes, cerrar sesión). Content starts with the timer bar at full width, then "Esta semana" with week total right-aligned, then day groups.

FORM: Category canon (user-chosen exit), seed 28ff623d.

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Adaptations

- Top bar left holds the workspace switcher instead of the page title, as Clockify does; the page title stays at the top of the content.
- Help and notifications are not in the top bar: the app has no help centre or notification system to back them.
- Sidebar group labels are sentence case (Seguimiento, Análisis, Gestionar), matching the rest of the UI.
- The "Facturable" toggle stays (user kept the billable flag, as in Clockify); only money values are hidden.
