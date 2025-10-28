# Elementor to Gutenberg

## Batch Convert Wizard

The plugin now exposes a **Batch Convert Wizard** in WP Admin that orchestrates bulk Elementor → Gutenberg conversions using the existing converter logic.

### Where to find it

1. Sign in with a user that can edit pages.
2. Go to **Elementor To Gutenberg Settings → Batch Convert Wizard**.

### Step-by-step flow

1. **Select Pages**
   * Use the familiar WP table to filter by status, search terms, Elementor data, or date range.
   * Tick the checkboxes for the pages you want or click **Select all matching filter** to queue every page that meets the active filters.
   * The wizard keeps your selection while you paginate and clearly shows how many pages are queued.
2. **Options**
   * Choose whether to update in place or create new Gutenberg copies.
   * Toggle optional behaviours: assign the Full-Width template, wrap the converted content, preserve an Elementor draft, keep featured image/meta, and skip pages already converted.
3. **Confirm & Run**
   * Review a summarised list of selections and options.
   * Start the conversion queue. Pages are processed one at a time over AJAX to avoid timeouts and you can cancel mid-run.
   * Progress shows a live log with success, skipped, or failed status for each page. Completed runs can be exported as CSV.

### Safety & resilience

* Every AJAX endpoint enforces the `edit_pages` capability and nonce protection.
* The queue is stored per-user, so a reload allows you to **resume** from the progress step without losing results. Cancelling the run clears the queue.
* Successful conversions record `_ele2gb_last_converted`, `_ele2gb_target_post_id`, and `_ele2gb_last_result` on the source page. Skipped/failed items store diagnostic messages using `_ele2gb_last_result` as well.
* A lightweight rolling log is kept in the `ele2gb_conversion_log` option for quick support lookups.

### Tips

* Selecting “Create new page” copies the Elementor page, appends “(Gutenberg)” to the title, and optionally carries over meta/featured images.
* Selecting “Update existing page” can create a draft backup of the original Elementor content before the converter overwrites the page.
* If you need to abandon a partially completed queue, click **Cancel** on the Confirm step to clear the stored state.
