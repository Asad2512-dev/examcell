# Design lineage and backend integration

The Figma Starter plan blocked further MCP reads, including design-context export. This implementation uses the saved design token/component/screen ledger, the previously reviewed dashboard screenshot, and the original ExamCell product brief. It is an editable, server-rendered Blade implementation; the original screenshots informed layout, rather than being used as UI backgrounds.

The application preserves the requested palette, expanded 248px sidebar, 72px desktop topbar, Inter typeface, 8-point spacing, restrained rounded cards, semantic status labels and role-specific information architecture. It adds reusable line icons, richer dashboard details and responsive behavior needed for the working frontend.

The Figma file had 112 completed Admin workflow screens plus foundations/components. The remaining role portals and modules were specified in the product brief and were first implemented as frontend screen specifications. The backend now resolves those URLs to authenticated domain views. This is not a claim that the original Figma file has been completed or that every page has a verified pixel-exact Figma export.

Some dialogs/workflow stages are addressable screens so developers can inspect them independently. They now share authenticated workflow forms and database query scopes. Live actions persist through controllers and services. The old demonstration partials remain as source references; the runtime renders `backend/*` views. No local browser state is used as authoritative academic data.
