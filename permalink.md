# Permalinks

Every view of YOUL VAT has a stable URL, so organisers and volunteers can bookmark it, share it by
SMS or e-mail, or reload the page and land in the same place. All links are relative to the app folder
(it works in a sub-folder such as `https://asso.bzh/regie/`).

## URL structure

| Target | URL | Opens |
|---|---|---|
| Event list | `index.php` | All events (no event selected) |
| Event planning | `index.php?event=<event>` | Planning tab, on the day with the most shifts |
| Planning on a day | `index.php?event=<event>&day=YYYY-MM-DD` | Planning tab on that day |
| Other tab | `index.php?event=<event>&tab=dash\|bens\|postes` | Dashboard, volunteer list, posts & shifts |
| **Shift** | `index.php?event=<event>&creneau=<creneau>` | The shift panel, planning on the shift's day |
| **Volunteer** | `index.php?event=<event>&benevole=<benevole>` | The volunteer's planning panel |
| **Post** | `index.php?event=<event>&tab=postes&poste=<poste>` | Posts tab, scrolled to the post, outlined in yellow |
| Sign-up | `inscription.php?event=<event>` | Public sign-up page |
| Sign-up on a shift | `inscription.php?event=<event>#c-<creneau>` | Sign-up page, scrolled to that shift |
| Contact | `contact.php?event=<event>` | Public contact page |

Optional parameter on every page: `lang=br|fr|en` (remembered in the browser afterwards).

`<event>`, `<creneau>`, `<poste>` and `<benevole>` are the `id` columns of the `events`, `creneaux`,
`postes` and `benevoles` tables. They never change when a name is edited, so a link stays valid until
the item is deleted.

### `event` can be omitted

`index.php?creneau=<id>`, `index.php?poste=<id>` and `index.php?benevole=<id>` work on their own. The
event is taken from the shift or the post. For a volunteer, it is the event of their most recent shift.
The page then puts `event=` back into the address bar.

### Unknown or deleted ids

- Unknown event: the event list is shown.
- Unknown shift, post or volunteer: the event opens normally (or the event list, if `event` is missing).

## Behaviour in the page

- Opening a shift or a volunteer (click) adds `creneau=` or `benevole=` to the URL (`history.pushState`).
  The **back** button closes the panel or returns to the previous one. For example, volunteer, then one of
  their shifts, then back returns to the volunteer.
- Closing a panel removes the parameter (`history.replaceState`).
- Changing tab drops `creneau`, `benevole` and `poste`.
- `day=` is only kept on the planning tab.

## Where links appear

Wherever a **post, volunteer or shift name is displayed with no other action**, the name is a link to its
permalink, followed by a small link symbol (chain icon, class `plink`). Cmd/Ctrl-click opens it in a new
tab; a plain click navigates inside the page without reloading.

| Place | Linked name |
|---|---|
| Planning, grid view | Post label of each row |
| Planning, list view | Post title of each group |
| "Retrouver une inscription" | Volunteer name |
| "Retrouver un créneau" | Post subtitle |
| Dashboard, fill rate per post | Post name |
| Posts & shifts tab | Post name on each card |
| Shift panel | Post name (title), volunteer names in the team |
| Sign-up page | Post titles, and each shift in "Mes créneaux" (link to the shift on the planning) |

Not linked, because the element already has an action:

- shift rows and grid blocks open the shift;
- rows of the volunteer list open the volunteer.

The shift and volunteer panels also have a **"Copier le lien"** button, which copies the full URL.

## Privacy

Permalinks are public like the rest of the planning. A volunteer link shows the same masked data as the
public planning: first name, initial of the last name, first characters of the e-mail and first and last
digits of the phone. The full details only appear to logged-in organisers.

## Code

| Where | What |
|---|---|
| `index.php`: `readUrl()` | Reads `event`, `tab`, `day`, `creneau`, `benevole`, `poste` |
| `index.php`: `pageUrl()` | Builds the current URL from the state (open panel included) |
| `index.php`: `applyLink()` | Opens what the URL asks for, works out the missing `event` |
| `index.php`: `openLink()` / `closeDr()` | Keep the URL in sync when a panel opens or closes |
| `index.php`: `permaUrl()`, `plink()` | Build a permalink and the linked name with its symbol |
| `inscription.php`: `permaUrl()`, `plink()` | Same, linking back to the planning |
| `assets/app.css`: `.plink`, `.plink-i`, `.postes .panel.hl` | Link style, symbol, highlighted post |
