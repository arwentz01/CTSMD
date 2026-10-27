# Community-wide search (web)

Route: `GET /channels/search?q=phrase`

- Search is available from the Community channel rail, including individual channel views.
- It searches all currently readable channels, not just those opted into the combined feed.
- The search scope is computed from `CommunityExperience::channels()`, which calls the existing `canRead()` authorization gate on each channel; hidden/inaccessible channels never enter the post query.
- Results are published posts only; hidden, deleted and archived content is excluded.
- Search phrases are literal, parameterized, limited to 100 characters and a maximum of 50 recent results.
- Each result links to a post anchor inside its channel. Read permission is rechecked when opening the target channel.
- No cross-user search index, caches or public unauthenticated search endpoints are introduced.
- Search does not include private messages or non-public moderation queues.

## QA before release
1. Run `php -l src/CommunityExperience.php` and `php tests/community_search.php`.
2. Create and search a known phrase in a public audience channel, a selected-member channel, a team channel, and a production group channel. Verify results for authorized accounts only.
3. Remove a guardian relationship or channel membership; re-search with the affected account and confirm no old results or counts remain.
4. Verify pending, hidden and deleted posts never match and an archived channel is omitted.
5. Search literal `%`, `_` and `!` characters, empty and one-character input, and a query longer than 100 characters.
6. Test jump-to-post behavior on desktop and mobile. Verify existing mobile Community screens are unaffected; native Expo search is not included in this web change.
7. Check response times with a realistic number of posts; if needed add a search-specific index or full-text strategy without compromising authorization.

GitHub commits contain source and a DB-stub search regression test. No CI run or staging-browser verification has been performed here.
