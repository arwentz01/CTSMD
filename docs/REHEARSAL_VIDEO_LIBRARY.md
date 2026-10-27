# CTSMD Rehearsal Library — Bluehost-local V1

**Git branch:** `audit/board-pilot-readiness`. **Status:** implemented in source only. Migration, PHP lint, browser upload/playback, and production deployment are not verified.

## User experience

- Open `/production/rehearsal-library` or the Rehearsal Library tile on the Production workspace.
- **Existing production staff permissions** (`production.manage`) allow upload, archive, restore and permanent removal. Do not introduce a dedicated choreographer role/permission.
- Staff choose a title, category, optional instructions, visibility for **entire production** or one active **Production Group**, then upload an MP4, WebM or MOV.
- Students and guardians with live production membership can view whole-production videos. Group-specific videos require active group membership or an active guardian relationship to an actively enrolled Student in that group. Former guardian relationships must not grant video access.
- Archived videos disappear from student/guardian lists and remain in staff storage usage. To reclaim space, archive a video and choose **Delete from storage**. This removes the file and its library record; do not confuse archive with physical deletion.
- Browser playback uses an authenticated PHP range-streaming endpoint (`/production/rehearsal-video?id=...`) with a fresh permission check on each request. Video files are not available by static public URL.

## Filesystem layout (Bluehost only)

`<CTSMD project root>/storage/private/rehearsal-videos/<production-id>-<production-slug>/<year>/<upload-timestamp>-<video-title-slug>-<random>.mp4`

Example: `storage/private/rehearsal-videos/42-beauty-and-the-beast/2026/20261027-184401-belle-ensemble-6c0bb8f2f03c.mp4`.

The numeric production ID avoids collisions when production names repeat or change. The production and video slugs make manual archive/export and deletion easy. A parent `.htaccess` file and another inside `rehearsal-videos` deny direct web access for Apache. The server must respect those rules; never allow direct HTTP access to the storage files. Storage lives on Bluehost's local filesystem, not an external provider or mount.

The library displays the current total database-recorded bytes for each production; it does not independently recalculate physical disk usage. **If files are manually deleted using File Manager/SSH, database records remain.** Playback will return 404. Prefer archival export followed by deletion using the staff interface. Make a backup before manually removing anything.

## Bluehost configuration

Check that the PHP limits allow the planned video size. Example for a 200 MB upload:

```ini
upload_max_filesize = 220M
post_max_size = 230M
max_file_uploads = 20
max_input_time = 600
max_execution_time = 600
memory_limit = 256M
```

The application sets `CTSMD_VIDEO_MAX_MB` to **200 MB by default** (configurable, capped at 1024 MB in code), and refuses larger files. These PHP settings are only examples; Bluehost may enforce lower shared-hosting limits independently. Streaming through PHP consumes PHP workers and site bandwidth. There is **no server-side FFmpeg transcoding** in V1. Staff should preferably upload compressed H.264 MP4 videos with AAC audio for broad browser playback. MOV may not play natively on all devices.

If this eventually stresses Bluehost storage, CPU, PHP workers, or bandwidth, the clear filesystem layout supports manual export before switching storage providers.

## Migration

Apply `database/migrations/091_rehearsal_video_library.sql`. This adds one `rehearsal_videos` metadata table; the actual video bytes are never stored in SQL. Previous schema remains unchanged. Retain the migration for environments already upgraded; the table will be merged into the planned canonical V1 schema on database reset.

## Safeguarding

- All content is production-private. No public playbill/cast pages, anonymous embeds, public URLs, or open download directories.
- Existing users may be staff **and** family members; the write permission uses the existing production staff access policy only.
- Production Group access is rechecked on each playback request, including guardian–Student relationships and active production membership.
- The `controlsList="nodownload"` browser hint is cosmetic, **not DRM**: anyone permitted to play a video may still record or save it. Families should be informed videos are for rehearsal use only.
- Avoid displaying student videos publicly; only authorized staff should upload content they are allowed to share with assigned production members.
- Avoid storing access tokens or credentials in filenames, notifications or public logs.

## Before piloting

1. Apply SQL 091, confirm creation on Bluehost, then open the Production workspace as staff.
2. Check server `post_max_size`, `upload_max_filesize`, body/WAF limits, PHP timeout, and total free account disk space.
3. Upload an MP4, inspect the production-name folder via File Manager, test seek/rewind in Chrome/Safari/mobile via HTTP Range.
4. Verify access as staff, Student, guardian, unrelated account, former guardian and a Student outside the assigned group. Test revocation mid-session (subsequent requests must deny).
5. Test archived videos are inaccessible to regular viewers; restore then archive and delete to reclaim disk. Confirm storage size changes and audit events exist.
6. Test custom files, spaces and non-ASCII titles, PHP upload failures, filesystem permission errors and missing/deleted files.
7. Verify Apache `.htaccess` protection explicitly: direct `/storage/private/rehearsal-videos/...` URL must never serve a video.
8. Perform PHP lint and full regression tests, including audit and access-policy checks; not yet executed in this GitHub-only integration.
9. Monitor hosting disk usage, PHP worker saturation and buffering. Set an operational archive cadence after each production.

**Deferred for V1:** automatic compression, chunked/resumable uploads, cloud delivery, automatic notifications, view acknowledgments, offline download, multiple target groups per video, and native-mobile-specific UI. The responsive web library is available to mobile browsers.
