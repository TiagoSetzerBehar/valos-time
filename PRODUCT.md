# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

The ~20 members of VALO, a team company inside the LEINN programme (Spain). They log the hours they spend on the company's projects and tasks while working on their laptops, mostly at a desk; phone use is secondary. One admin (Tiago) manages members, projects and the organization.

## Product Purpose

VALO's Time is the team's own time tracker. Its job is internal organization: seeing who is working on what, how time is spread across projects, and balancing the load between members. Success means everyone logs their hours with almost no friction, and the team can read the week at a glance.

## Positioning

A self-hosted fork of the open-source solidtime, shaped around how Clockify works (timer, manual entry, timesheet, calendar, summary/detailed/weekly reports), without paid tiers, billing or money. Owned by the team, in Spanish, with no per-seat cost.

## Operating Context

- Members start/stop a timer or add time manually while working, then review their week in the timesheet/calendar.
- The admin reviews reports (summary, detailed, weekly by member) to see the distribution of work.
- Reminders: a daily email at the end of the day to members who logged nothing, and a weekly summary email to the admin listing who stayed below the expected hours.

## Capabilities and Constraints

- Stack: solidtime (Laravel 12 + Inertia + Vue 3 + Tailwind), Docker/Sail for development; must stay mergeable with upstream solidtime where possible.
- UI language: Spanish (Spain, "tú"). Translation via laravel-vue-i18n (lang/es.json).
- Money is hidden (VALO_SHOW_MONEY=false): no rates, costs or currency. The "Facturable" flag stays.
- Dark theme by default, with a quick light/dark toggle.
- Settings and profile live in the top-right of the app, like Clockify.
- Undecided: expected hours per member per week (needed for the admin summary); hosting/deploy target.

## Brand Commitments

- Name: "VALO's Time". No logo or colours exist yet; do not invent a logo mark that implies an existing brand.
- The user wants Clockify's structure but explicitly NOT its dated look, and wants the current "AI-generated" feel removed.

- Visual direction chosen by the user: the category standard (a modern Clockify), at Toggl Tracks level of craft but more sober and professional, with little colour.

## Evidence on Hand

No testimonials, metrics or brand assets. Only seeded demo data locally; never present demo data as real team data.

## Product Principles

1. Logging time must take seconds: the timer and manual entry are always one click away.
2. The week is the unit of reading: every view should make "this week" obvious.
3. Calm, dense, legible: a work tool used all day, not a showcase.
4. Familiar to Clockify users, without copying its dated visuals.
5. Nothing about money unless the team decides to turn it on.
