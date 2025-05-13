# Hibob Job Listings

## Description

The Hibob Job Listings plugin seamlessly integrates your Hibob HR platform job postings with your WordPress website using the official Hibob API. Display current job openings, detailed job information, and application links with simple shortcodes.

This plugin connects directly to the Hibob API to ensure your career page always shows up-to-date job listings, synchronizing your HR system and website automatically.

## Features

- **Job Listings Display**: Show available positions with filtering options via shortcode attributes.
- **Detailed Job Pages**: Present comprehensive job information fetched directly from Hibob.
- **Responsive Design**: Basic mobile-friendly layouts via included CSS.
- **Built-in Pagination**: Navigate through multiple job listings on the main listing page.
- **Direct Application Links**: Sends candidates directly to your Hibob application form from the details page.
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
    *   Edit the "Careers" page and add the `[hibob_job_listings]` shortcode, making sure to include the `job_details_page` attribute pointing to your "Job Details" page URL.
    *   Edit the "Job Details" page and add the `[hibob_job_details]` shortcode.

## Shortcodes

### Job Listings Shortcode

```
[hibob_job_listings job_details_page="https://yoursite.com/job-details/" limit="10" department="Sales" preferred_language="en"]
```

Place this on your main careers page.

#### Available Parameters:

*   `job_details_page` (string, **required**): The *full URL* of the page where you placed the `[hibob_job_details]` shortcode.
*   `limit` (int, optional): Number of jobs to display per page. Default: `10`.
*   `offset` (int, optional): Number of jobs to skip (used automatically for pagination). Default: `0`.
*   `preferred_language` (string, optional): Language code for job data (e.g., 'en', 'fr', 'de'). Default: `'en'`.
*   **Filtering Attributes (Optional):**
    *   `department` (string): Filter by department. **Note:** According to Hibob docs, you might need to use the *Department ID* from Hibob, not the name (e.g., `department="DPT123"`). Check your Hibob setup or use the Metadata API to find IDs if names don't work.
    *   `employment_type` (string): Filter by employment type. **Note:** Might require the *Employment Type ID* (e.g., `employment_type="EMP456"`).
    *   `site_id` (string): Filter by a specific site ID from Hibob (e.g., `site_id="SITE789"`).
    *   `location` (string): Filters by location. **Note:** This maps to the `siteId` filter. Use the *Site ID*.
    *   `language_code` (string): Filter jobs by their language code (e.g., `language_code="en-US"`).
    *   `keywords` (string): Filters jobs where the keyword appears in the title.
    *   `status` (string): Filter by job status. **Note:** Might require a specific status ID or code from Hibob (e.g., `status="Published"`).


### Job Details Shortcode

```
[hibob_job_details]
```

No parameters needed. This shortcode automatically retrieves the job ID from the URL parameter (`?job_id=123`).

## Example Setup

1. **Create a Job Listings Page:**
   
   Add this shortcode to your main careers page:
   ```
   [hibob_job_listings job_details_page="https://yoursite.com/job-details" department="Engineering" limit="5"]
   ```

2. **Create a Job Details Page:**
   
   Add this shortcode to your job details page:
   ```
   [hibob_job_details]
   ```

3. When users click on "View Job Details" from the listings page, they'll be taken to the details page with the job information displayed.

## Customization

### CSS Styling

The plugin includes basic styling that works with most themes. You can further customize the appearance by adding custom CSS to your theme or using a custom CSS plugin.

Key CSS classes:
- `.hibob-job-listings-container`: Main container for job listings
- `.hibob-job-item`: Individual job listing
- `.hibob-job-title`: Job title style
- `.hibob-job-meta`: Job metadata (department, location)
- `.hibob-job-details-container`: Container for detailed job view
- `.hibob-pagination`: Pagination controls

### Template Customization

Advanced users can create custom templates by copying and modifying the plugin's shortcode render functions in their theme's functions.php file.

## Frequently Asked Questions

**Q: Do I need a Hibob account?**  
A: Yes, this plugin requires API credentials from your Hibob account.

**Q: Can I customize the job listings display?**  
A: Yes, you can customize the display using the shortcode parameters and CSS styling.

**Q: How often are job listings updated?**  
A: Job listings are fetched in real-time from the Hibob API when a user visits your page.

**Q: Can I add application forms directly on my website?**  
A: The plugin provides an "Apply Now" button that links to your Hibob application system. For custom forms, you'd need additional development.

**Q: Does this work with any WordPress theme?**  
A: Yes, the plugin is designed to work with any properly coded WordPress theme.

## Troubleshooting

**Job listings aren't showing:**
1. Check that your API credentials are correct
2. Verify you've included the required `job_details_page` parameter in the listings shortcode
3. Check if there are actually jobs published in your Hibob system

**Error messages:**
- "Hibob API credentials are not configured": Set up your API credentials in the plugin settings
- "No job ID specified": Make sure your job details URL includes the job_id parameter
- "Error fetching job listings": Check API credentials and connection to Hibob

## Support

For support inquiries, feature requests, or bug reports, please contact:

- Email: support@konceptslab.com
- Website: https://konceptslab.com/support

## Changelog

### 1.0.0
- Initial release with core functionality
- Job listings display with filtering options
- Detailed job view with application link
- Admin settings for API credentials

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