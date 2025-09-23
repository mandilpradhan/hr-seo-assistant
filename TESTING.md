# HR SEO Assistant — Phase 0 Testing Checklist
Version: 0.1.0

## Goal of Phase 0
- Plugin scaffold works (menus, settings, feature flags, debug).
- JSON-LD emitters adopted into the plugin produce **parity** with legacy MU output.
- **OG/Twitter is OFF** in this phase.

---

## 0) Pre-checks
- ✅ Plugin “HR SEO Assistant” is **Activated**.
- ✅ Legacy schema MUs still active in `/wp-content/mu-plugins/` (reference).
- ✅ Repo contains `legacy-mu/` (read-only source for Codex).
- ✅ You can access: `HR SEO → Overview`, `Settings`, `Modules`.
- 🚫 No OG/Twitter tags should be emitted yet.

---

## 1) Settings sanity
1. Go to **HR SEO → Settings**.
2. Fill these (any sensible values for now):
   - **Fallback Image (sitewide):** pick a real URL via Media Picker.
   - **Title templates:**
     - Trips: `{{trip_name}} | Motorcycle Tour in {{country}}`
     - Pages: `{{page_title}}` (leave “Append brand suffix” OFF for Phase 0)
   - **Locale:** `en_US`
   - **Site name:** `Himalayan Rides`
   - **Twitter handle:** `@himalayanrides` (optional)
   - **Image preset:** `w=1200,fit=cover,gravity=auto,format=auto,quality=75`
   - **Conflict mode:** **Respect**
   - **Debug mode:** **ON**
3. Save. You should see a success notice.

---

## 2) Debug page (should appear because Debug = ON)
Navigate: **HR SEO → Debug**  
Verify:
- **Environment:** Post ID / Type / Template, Current URL, Conflict Mode, Flags (jsonld/og/debug).
- **Context:** shows at least `url, type, site_name, locale, twitter_handle, hero_url (may be null)`.
- **Connectors:** “Hero filter” present? (OK if **No** for now.)
- **Settings snapshot:** values you saved above.
- **Modules:** JSON-LD (enabled), OG (disabled).
- **No errors/warnings**.

---

## 3) No OG/Twitter emission
Open a page and **View Source**. Confirm:
- There is **no** `<meta property="og:title">` emitted by HR SEO Assistant.
- There is **no** `twitter:` meta emitted by HR SEO Assistant.  
(Other plugins may output theirs if installed; that’s fine in Phase 0.)

---

## 4) JSON-LD parity — capture current (legacy) output
Pick three URLs:
- **Trip page** (with FAQ + Itinerary if possible)
- **Generic page** (About/Contact)
- **Homepage**

For each URL, capture current JSON-LD (legacy MUs):
```bash
# Replace with actual URLs
URL="https://himalayanrides.com/trip/bhutan-motorcycle-tour-chasing-the-thunder-dragon/"
curl -s "$URL" | perl -0777 -ne 'print "$1\n" while (/\<script type="application\/ld\+json"\>(.*?)\<\/script\>/sg)' > /tmp/legacy-trip.json

URL="https://himalayanrides.com/"
curl -s "$URL" | perl -0777 -ne 'print "$1\n" while (/\<script type="application\/ld\+json"\>(.*?)\<\/script\>/sg)' > /tmp/legacy-home.json
```

> Note: Some pages output **multiple** JSON-LD blocks. Saving the concatenation is fine—we’ll compare normalized JSON, not formatting.

---

## 5) Switch to plugin JSON-LD (temporarily disable legacy)
**Because MU-plugins always load**, do ONE of these on staging:

- Easiest: **Temporarily rename** each relevant MU file in `/wp-content/mu-plugins/` from `*.php` → `*.ph_` (so WP won’t load it).  
  Example:
  ```bash
  cd /path/to/wp-content/mu-plugins
  mv hr-schema-core.php hr-schema-core.ph_
  mv hr-trip-schema-graph.php hr-trip-schema-graph.ph_
  mv hr-wte-vehicle-offers.php hr-wte-vehicle-offers.ph_
  ```

- OR move them into a `disabled-mu/` folder temporarily.

Ensure HR SEO Assistant’s JSON-LD module is **enabled** (it should be by default in Phase 0).

---

## 6) JSON-LD parity — capture plugin output
Use the same URLs as step 4:
```bash
URL="https://himalayanrides.com/trip/bhutan-motorcycle-tour-chasing-the-thunder-dragon/"
curl -s "$URL" | perl -0777 -ne 'print "$1\n" while (/\<script type="application\/ld\+json"\>(.*?)\<\/script\>/sg)' > /tmp/plugin-trip.json
```

---

## 7) Compare outputs (normalized)
If `jq` is available:
```bash
jq -S . /tmp/legacy-trip.json  >/tmp/legacy-trip.norm.json 2>/dev/null || cp /tmp/legacy-trip.json  /tmp/legacy-trip.norm.json
jq -S . /tmp/plugin-trip.json  >/tmp/plugin-trip.norm.json 2>/dev/null || cp /tmp/plugin-trip.json  /tmp/plugin-trip.norm.json
diff -u /tmp/legacy-trip.norm.json /tmp/plugin-trip.norm.json || echo "Trip JSON-LD: differences shown above"
```

---

## 8) Restore legacy MUs (until Phase 1)
Rename MU files back to `*.php` so production behavior is unchanged until we ship OG in Phase 1.

---

## 9) Final checks
- **HR SEO Assistant** remains active.
- **Debug** page shows JSON-LD module enabled, OG disabled.
- No PHP notices/fatals in error logs during page loads.

✅ **Phase 0 is complete** when:
- JSON-LD parity is verified on the three page types.
- Admin UI & Debug behave correctly.
- No OG/Twitter emitted by our plugin yet.
