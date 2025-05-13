# Hibob Job Listings

## Description

The Hibob Job Listings plugin seamlessly integrates your Hibob HR platform job postings with your WordPress website using the official Hibob API. Display current job openings, detailed job information, and application links with simple shortcodes.

This plugin connects directly to the Hibob API to ensure your career page always shows up-to-date job listings.

**Current Version:** 1.2.1

## Features

- **Job Listings Display**: Show available positions with highly customizable filtering options via shortcode attributes, including advanced JSON-based filters.
- **Customizable Fields**: Specify which job data fields you want to retrieve and display.
- **Detailed Job Pages**: Present comprehensive job information fetched directly from Hibob.
- **Responsive Card Design**: Modern, responsive card layout for job listings.
- **Job Details Page Styling**: Clean and readable layout for individual job details.
- **Built-in Pagination**: Basic "Next/Previous" pagination for navigating job listings. (Effectiveness of server-side pagination depends on Hibob API support for limit/offset on the search endpoint).
- **Direct Application Links**: Sends candidates directly to your Hibob application form.
- **Admin Settings**: Configure API credentials easily.

## Installation

1.  **Upload the Plugin**
    *   Download the `hibob-job-listings.zip` file.
    *   Navigate to your WordPress dashboard: Plugins > Add New > Upload Plugin.
    *   Select the downloaded zip file, click Install Now, and then Activate.

2.  **Configure API Credentials**
    *   Go to Settings > Hibob API Settings in your WordPress admin area.
    *   Enter your Hibob API username (Service User ID) and password (Token).
    *   Save changes. **Ensure the service user has permissions to read hiring data.**

3.  **Create Pages**
    *   Create a WordPress page for your main job listings (e.g., "Careers" or "Open Positions").
    *   Create a *separate* WordPress page for viewing job details (e.g., "Job Details"). Note the URL (permalink) of this page.

4.  **Add Shortcodes**
    *   Edit your "Careers" (or equivalent) page and add the `[hibob_job_listings]` shortcode, ensuring you include the required `job_details_page` attribute pointing to your "Job Details" page URL.
    *   Edit your "Job Details" page and add the `[hibob_job_details]` shortcode.

## Shortcodes

### Job Listings Shortcode: `[hibob_job_listings]`

Place this on your main careers/job listings page.

**Basic Usage:**
```html
[hibob_job_listings job_details_page="https://yoursite.com/job-details/"]
```

**Advanced Usage with Custom Fields and Filters:**
```html
[hibob_job_listings
    job_details_page="http://localhost:8888/wordpress/index.php/job-details"
    request_fields="/jobAd/title,/jobAd/id,/jobAd/location"
    advanced_filters='[{"fieldId":"/jobAd/id","operator":"notEqual","values":["TestIDToExclude"]}]'
    limit="6"
    preferred_language="en-GB"
]
```

#### Available Parameters:

*   `job_details_page` (string, **required**): The *full URL* of the page where you placed the `[hibob_job_details]` shortcode.
*   `limit` (int, optional): Number of jobs to display per "page" if using pagination. Default: `9`. (Note: The Hibob `/search` API endpoint might not support `limit`/`size` request parameters; pagination might be based on the number of results received).
*   `offset` (int, optional): Number of jobs to skip. Primarily used by the plugin's pagination links. Default: `0`.
*   `preferred_language` (string, optional): Language code for job data (e.g., 'en', 'fr', 'de'). Default: `'en'`.
*   `request_fields` (string, optional): A comma-separated list of Hibob field paths you want to retrieve (e.g., `"/jobAd/title,/jobAd/id,/jobAd/customField_XYZ"`). If provided, these fields (plus essential display fields like ID, title, location) will be requested. If omitted, a default set of fields is requested by the plugin.
*   `advanced_filters` (string, optional): A **JSON string** representing an array of Hibob filter objects. This allows for complex filtering. If this attribute is provided with valid JSON, it **overrides** all standard filter attributes below.
    *   Example: `advanced_filters='[{"fieldId":"/jobAd/siteId","operator":"equals","values":["NewYorkOfficeID","LondonOfficeID"]},{"fieldId":"/jobAd/status","operator":"equals","values":["Published"]}]'`
    *   To fetch all jobs (no filters): `advanced_filters='[]'`
*   **Standard Filter Attributes (Optional - Ignored if `advanced_filters` is used and valid):**
    *   `department` (string): Filter by department. **Note:** Likely requires the *Department ID* from Hibob (e.g., `department="DPT123"`).
    *   `employment_type` (string): Filter by employment type. **Note:** Likely requires the *Employment Type ID* (e.g., `employment_type="EMP456"`).
    *   `site_id` (string): Filter by a specific site ID from Hibob (e.g., `site_id="SITE789"`).
    *   `location` (string): Filters by location. **Note:** This maps to the `siteId` filter. Use the *Site ID*.
    *   `language_code` (string): Filter jobs by their language code (e.g., `language_code="en-US"`).
    *   `keywords` (string): Attempts to filter jobs where the keyword appears in the job title (uses an "equals" operator by default).
    *   `status` (string): Filter by job status (e.g., `status="Published"`). **Note:** Might require a specific status ID or code.

**Important Note on `request_fields` and `advanced_filters`:**
If you are using a version of the plugin where `includes/class-hibob-api.php` has the API request body for `search_job_listings` hardcoded (as was done for specific testing), the `request_fields`, `advanced_filters`, and standard filter attributes in the shortcode will be **ignored** by the API call. The hardcoded request in the PHP file will take precedence.

### Job Details Shortcode: `[hibob_job_details]`

Place this on your dedicated job details page.

```html
[hibob_job_details]
```

*   No parameters are typically needed. It automatically retrieves the `job_id` from the URL query string (e.g., `...?job_id=JOB123XYZ`).
*   Optional: `lang="en-US"` could be added in the future if language control for details is needed.

## Example Setup

1.  **Create "Job Details" Page:**
    *   Title: "Job Description" (or similar).
    *   Content: `[hibob_job_details]`
    *   Publish and note its full URL (e.g., `https://yoursite.com/job-description/`).

2.  **Create "Careers" Page:**
    *   Title: "Careers" (or similar).
    *   Content:
        ```html
        [hibob_job_listings
            job_details_page="https://yoursite.com/job-description/"
            limit="6"
            request_fields="/jobAd/title,/jobAd/id,/jobAd/location,/jobAd/department"
            department="YOUR_ENGINEERING_DEPT_ID"
        ]
        ```
    *   Replace `YOUR_ENGINEERING_DEPT_ID` with an actual ID.
    *   Publish.

## Styling

The plugin includes CSS for a modern card layout for listings and a clean layout for job details. You can override these styles in your theme's `style.css` or via the WordPress Customizer's "Additional CSS". Refer to `assets/css/hibob-jobs.css` for class names.

## Troubleshooting

*   **"Hibob API credentials are not configured" / "Invalid Hibob API credentials..."**: Check Settings > Hibob API Settings. Ensure correct Service User ID, Token, and that the service user has hiring data read permissions.
*   **"Error: The 'job_details_page' attribute is missing..."**: Add the `job_details_page="YOUR_URL"` attribute to `[hibob_job_listings]`.
*   **"No job ID specified" (on details page)**: The URL is missing `?job_id=...`. Check links from the listings page.
*   **"Job not found" (on details page / 404 error)**: The job ID is invalid or the job is no longer active.
*   **"Error: Bad request to Hibob API (400)..."**:
    *   If using `advanced_filters`, ensure your JSON is perfectly valid. Use an online JSON validator.
    *   Check that `fieldId`s are correct (e.g., `/jobAd/departmentId` not just `department`).
    *   Ensure `values` for filters are in an array (e.g., `"values":["Value1"]`).
    *   If using `request_fields`, ensure field paths are correct (e.g., `/jobAd/title`).
    *   The API message might provide more clues (e.g., "fields: Array value expected").
*   **"Error fetching job listings: Hibob API Error (500) – General Error"**: This usually indicates an issue on Hibob's server side.
    *   Try simplifying your request (e.g., remove all filters by using `advanced_filters='[]'`, or request fewer fields with `request_fields="/jobAd/id,/jobAd/title"`).
    *   Test the failing request directly using cURL or Postman.
    *   If the issue persists with a valid-looking direct API call, contact Hibob support with your request details.
*   **Jobs not filtering as expected:**
    *   For `department`, `location`, `employment_type`, `site_id`, `status`, the Hibob API often requires specific **IDs**, not names. Consult your Hibob system or API documentation.
*   **Stray characters like `}]’]` appearing on the page:** This is often caused by unbuffered `print_r` or `var_dump` statements in your theme files (`functions.php`, page templates) or another plugin. Temporarily switch to a default theme and disable other plugins to isolate.

**Debugging Tip:** Enable `WP_DEBUG` and `WP_DEBUG_LOG` in your `wp-config.php`. Check `wp-content/debug.log` for detailed error messages from the plugin, including the raw API responses and request bodies being sent.

## Frequently Asked Questions

**Q: Why are my filters (department, location) not working with names?**
A: The Hibob API generally requires specific IDs (e.g., Department ID, Site ID) for these types of filters, not just the textual names. You'll need to find these IDs in your Hibob system or via their Metadata API. Use these IDs in the shortcode attributes or within the `advanced_filters` JSON.

**Q: How does pagination work?**
A: The plugin displays "Next/Previous" links. The actual fetching of distinct pages of results from the server depends on whether the Hibob `/hiring/job-ads/search` endpoint respects `limit` (size) and `offset` (from) parameters in the API request. The plugin is set up to handle this if the API supports it, but current information suggests the `/search` endpoint might not use these for pagination in the request itself.

**Q: Can I change the fields displayed on the job cards or details page?**
A: For job listings, use the `request_fields` attribute in the `[hibob_job_listings]` shortcode to specify which fields to fetch. You'll then need to ensure your plugin's PHP and CSS can display these fields. For the job details page, this would require modifying the `render_job_details_shortcode` method in the plugin's PHP code.

## Support

For support inquiries, feature requests, or bug reports, please contact Koncepts Lab.

## Changelog

### 1.2.1 
- FIX: Addressed "fields: Array value expected" by ensuring the `fields` parameter is always a valid array.
- ADD: Logging for request bodies and raw API responses to aid debugging.
- UPDATE: README with advanced shortcode examples and improved troubleshooting.
- CHORE: Refined error message display from API.

### 1.2.0
- ADD: `request_fields` and `advanced_filters` shortcode attributes for more control over API requests.
- UPDATE: Card layout for job listings.
- UPDATE: Styling for job details page.
- FIX: Correct parsing of Hibob API response structure `[ { "/jobAd/field": { "value": "..." } } ]`.



## Credits

Developed by [Koncepts Lab](https://konceptslab.com/)

## License

This plugin is licensed under the GPL v2 or later.
```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```
