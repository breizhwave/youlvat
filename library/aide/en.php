<?php
/* Help page, English version. Same outline and anchors (id) as fr.php, the reference text: keep them in sync. */
?>
<h1>Help</h1>
<p class="lead">Youl Vat is the festival’s volunteer schedule: anyone can see the shifts and sign up without an account; organisers set up the roles, assign volunteers and check them in.</p>

<nav class="toc" aria-label="Contents">
  <a href="#benevoles">Volunteers</a> · <a href="#confidentialite">Personal data</a> · <a href="#organisateurs">Organisers</a> · <a href="#jourj">On the day</a> · <a href="#historique">Removal history</a> · <a href="#liens">Direct links</a> · <a href="#technique">Technical team</a>
</nav>

<h2 id="benevoles">For volunteers</h2>
<h3>Viewing the schedule</h3>
<ul>
  <li>Pick the event in the menu at the top. The <b>Schedule</b> tab opens on the busiest day; switch days with the buttons above the grid.</li>
  <li>Two views: <b>Grid</b> (timeline) and <b>List</b> (shifts grouped by role, the default on phones). Your choice is remembered.</li>
  <li>Shift colour: <b>green</b> = full, <b>orange</b> = people still needed, <b>red</b> = nobody yet.</li>
  <li>A shift starting before 6 am belongs to the night of the given day: “31 December, 00:00–05:00” is the night of the 31st to the 1st.</li>
  <li>On a phone, the yellow <b>☰</b> button next to the languages opens the page menu.</li>
</ul>
<h3>Signing up</h3>
<ol>
  <li><b>Sign up</b> button (or “Sign up as a volunteer” in the box on the schedule).</li>
  <li>Choose a free shift, then give your first name, last name, phone and e-mail (all required). You can add a remark for the organisers.</li>
  <li>For more shifts, sign up again with the same details: your record is reused.</li>
</ol>
<p>Sign-up is refused if the shift has just filled up, if you are already on it, or if you are already busy at that time.</p>
<h3>Finding my shifts</h3>
<p>At the top of the Schedule, “Find a sign-up”: type your first and last name (“marie dupont”), or your <b>exact</b> e-mail or phone. “Find a shift” searches by role, lead, day or time (“bar 31 22h”).</p>
<h3>Changing or withdrawing</h3>
<p>There is no online withdrawal: the red <b>Change / withdraw</b> button leads to the event’s <b>Contact</b> page.</p>
<h3>Becoming a lead</h3>
<p><b>Leads</b> tab: while a lead post still has room, the <b>I’ll do it</b> button leads to a sign-up form. Being a lead does not sign you up for the post’s shifts.</p>

<h2 id="confidentialite">Personal data</h2>
<ul>
  <li>The public only sees your first name, the initial of your last name and a few characters of your contact details, enough to tell two namesakes apart: “Marie D. ******* · ma****@**** · 06******78”.</li>
  <li>Your full phone number, e-mail and remarks are visible to organisers only.</li>
  <li>The details on the Contact page belong to the organisers: they are public.</li>
</ul>

<h2 id="organisateurs">For organisers</h2>
<h3>Logging in</h3>
<p><b>Organisers</b> button (under the languages), with the team’s shared password. The editing buttons then appear. Data reloads every 15 seconds and after each change.</p>
<h3>Event</h3>
<ul>
  <li><b>+ Event</b> / <b>Edit</b> next to the event menu: name, place, dates, image (https address), contact details, running order of the evening (one step per line, “20:00 Doors open”).</li>
  <li>The “Want to lend a hand?” box (Schedule, Dashboard) and the Contact page text are edited in place (<b>Edit</b> button), one version per language: switch language at the top of the page to write the other version. Without a translation, the French text is shown.</li>
</ul>
<h3>Roles and shifts</h3>
<ul>
  <li><b>Roles &amp; shifts</b> tab: <b>+ Role</b> (name, colour, instructions), then <b>+ Shift</b> on the role’s card (day, start, end, number of people needed).</li>
  <li>End before start = the shift runs past midnight. Start before 6 am = night of the given day (see above).</li>
  <li>Deleting a role deletes its shifts and their assignments (kept in the history).</li>
</ul>
<h3>Assigning a volunteer</h3>
<ul>
  <li>Click a shift in the schedule. In “Assign a volunteer”, type a few letters of the name, e-mail or phone, then <b>Enter</b>: the person is assigned and the cursor returns to the field so you can carry on.</li>
  <li>Candidates are sorted by hours already scheduled (least busy first); those already busy at that time are greyed out. An overlap is flagged in red.</li>
  <li><b>Remove</b> takes the person off the shift: their record stays in the directory and the removal is kept in the history.</li>
</ul>
<h3>Volunteer directory</h3>
<ul>
  <li><b>Volunteers</b> tab: every volunteer, including those with no shift (any more) (“available”). Click a header to sort; by default, most recent sign-ups first.</li>
  <li><b>+ Volunteer</b> for a record entered by hand (phone and e-mail optional). Click a row to see their schedule; “Copy their schedule” copies their shifts, ready to send by text or e-mail.</li>
  <li><b>Export CSV</b> (volunteers × shifts of the event) and <b>JSON backup</b> (the whole database, history included).</li>
</ul>
<h3>Leads</h3>
<p><b>Leads</b> tab → <b>+ Lead</b>: title, number of people, description, link to the job description, shifts covered. One person per line as “name, phone, e-mail” (phone and e-mail optional, in any order), or “+ Add a volunteer” who has already signed up. Leads appear under the role names in the schedule.</p>

<h2 id="jourj">On the day</h2>
<ul>
  <li>In a shift’s panel, each volunteer has a status: <b>planned</b>, <b>present</b> or <b>absent</b>. Absent volunteers no longer count towards filling or hours.</li>
  <li>The <b>Dashboard</b> shows filling, places to fill first, scheduled hours and the running order of the evening.</li>
  <li>Several organisers can work at the same time: the database handles simultaneous writes, and a sign-up never goes over the number of places.</li>
</ul>

<h2 id="historique">Removal history</h2>
<p>Nothing is lost when someone is removed. Each removal is copied (name, phone, e-mail, shift, status, sign-up date and removal date):</p>
<ul>
  <li>volunteer removed from a shift, or removed because the shift, role or event was deleted;</li>
  <li>person removed from a lead post;</li>
  <li>volunteer record deleted (full copy, remarks included).</li>
</ul>
<p>Where to see it: at the bottom of the <b>Volunteers</b> tab, “Removal history” (for the selected event, plus deleted records), and in each volunteer’s panel. Visible to organisers only; included in the JSON backup.</p>

<h2 id="liens">Direct links</h2>
<p>Every role, shift or volunteer name followed by the 🔗 symbol is a link you can bookmark or send. Panels have a “Copy link” button. A link to a volunteer only shows their full contact details to logged-in organisers.</p>

<h2 id="technique">For the technical team</h2>
<ul>
  <li>PHP 8 + SQLite, no installation: upload the folder by FTP. The password is set in <code>config.php</code>.</li>
  <li>Backups: <b>JSON backup</b> (Volunteers tab), not an FTP copy of the database while it is in use.</li>
  <li>Before each release: <code>php test/run.php</code> (automatic tests), then the procedure in <code>test/PROCEDURE.md</code>. The <code>test/</code> folder is not deployed.</li>
</ul>
