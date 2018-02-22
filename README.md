# Primary Taxonomy Term
Primary Taxonomy Term adds the ability to specify a primary term for any taxonomy.

# Developer Notes
The initial release will be a minimum viable product (MVP). Additional features will be added as time allows. This README will be modified to reflect progress after each release.

## Admin Considerations
### Plugin Settings
- Option – Checkbox to bubble primary content to top of archive pages

### Post Meta Controls
- Populate primary term select box with selected terms via JS (MVP)
- Option – Default to first term selected for each taxonomy
- Option – Require selection of primary term

### Data Integrity
- Unset primary term on post save when if the term no longer exists (MVP)
- Option – Warn when deleting a term currently used as primary term

## Frontend Use Cases
- Query for related content based on primary term (MVP)
- Add a custom class to primary content for targeting (MVP)
- Option – Shortcode to embed primary related content in any post
- Option – Widget to display primary related content in any sidebar

## Future Considerations
- MVP will focus on categories initially but the code should be extensible to apply to any taxonomy
- Internationalization (not an immediate concern for MVP)
- Taxonomy list view could include a new column called “Primary” to show how many posts have flagged the term as a primary term (quick way to ensure every cat has primary content associated with it).
