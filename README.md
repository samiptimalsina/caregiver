## Installation Process

- open project and install the project using installer

## Import Nirvan Caregiver Content

The public course catalog, blog posts, informational pages (including the homepage), brand logo, and first-party media snapshot are stored locally in `database/seeders/data/nirvan-caregiver-content.json` and `public/uploads/nirvan-caregiver/`. The seeder fills every LMS theme's existing hero, feature, and about sections with imported copy and local media, and makes the imported courses available to theme course sections while preserving each theme's current frontend design. The business theme uses the full imported carousel; the other themes use their existing single-hero layouts. Functional account/checkout pages are excluded; non-local embedded images are omitted.

Run the import after migrations with:

```bash
php artisan db:seed --class="Database\\Seeders\\NirvanCaregiverContentSeeder" --force
```

The import is safe to rerun and is also included in `php artisan db:seed`. Courses without a listed numeric price use a placeholder price of `1.00` in the configured currency; update it before accepting payments.
