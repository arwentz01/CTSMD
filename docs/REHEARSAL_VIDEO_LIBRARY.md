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

## Video Library audience expansion (migration 092)

The feature is now called **Video Library** at `/videos` (the old `/production/rehearsal-library` URL remains supported). CTSMD members can find it in the application navigation. **Staff upload/manage using existing `production.manage`; no choreographer role has been introduced.**

Staff choose exactly one audience at upload time:

1. **Everyone on CTSMD** — any authenticated, eligible CTSMD account can view the video, regardless of production. Do not place sensitive production-specific student footage in this audience.
2. **Entire selected production** — active members of that show, including active guardians linked through a participating Student. This is broader than a Production Group.
3. **Specific Production Group** — a narrower subset of the selected production, with authorized guardians of its Students. A group is an actual membership list within a production, not a synonym for the entire cast.

Existing rehearsal videos keep their original access boundaries. Apply `092_video_library_audience_scope.sql` **after** `091_rehearsal_video_library.sql`. The migration allows a NULL `production_id` for organization-wide videos and sets `audience_scope`. It is designed to be rerunnable without rewriting the audiences of videos created after it.

Organization videos are stored in:

```text
storage/private/rehearsal-videos/ctsmd-platform/2026/
```

Production videos continue to use the production-ID and name folder structure. The library shows separate organization-wide and selected-production logical usage totals for staff; those totals still depend on database metadata, not direct disk scans.

Known V1 limitations: one target group per video, no automatic compression or upload progress recovery, and organization-wide content is visible to all eligible CTSMD accounts rather than anonymously to the internet. This is not public video hosting.

**Release testing additions:** check accounts with no current production can still find and watch organization videos; verify whole-production content is inaccessible to other shows; group-specific videos are hidden from unrelated Students and former guardians; confirm SQL 092 applies after 091 without changing older group assignments; verify archive/restore/delete on NULL-production organization videos. These changes are committed but not runtime-verified.

## Bluehost storage export and archive operations

A staff-only Video Library storage summary now shows per-production video counts, stored bytes, and archived bytes. The totals are based on database records rather than scanning the physical disk. Archived videos continue consuming space until permanently deleted.

- **Download original** appears alongside staff controls on both active and archived videos. It uses `/videos/archive-download?id=<id>`, verifies the staff account and selected production context before streaming, and responds with an attachment filename derived from the identifiable private file path. Students and guardians cannot use this archive download.
- **Export inventory CSV** uses `/videos/inventory` for authorized staff and lists all productions and CTSMD-wide videos, including relative storage keys, creation timestamps, statuses, size and category. This helps match downloaded originals or SFTP/Bluehost File Manager copies to records before deletion. CSV text fields are escaped against spreadsheet formula interpretation.
- For efficient whole-production backups, copy the entire folder directly using Bluehost File Manager or SFTP and save the accompanying CSV. Downloading individual files through PHP is suitable for occasional backup but is **not** a substitute for bulk filesystem access on a shared hosting plan.
- Retention workflow: export inventory → back up the relevant production folder → verify copied files → archive videos in CTSMD → permanently delete archived videos through CTSMD when safe to reclaim space. Keep any youth media in a restricted archive.
- Physical deletion directly through Bluehost without updating CTSMD leaves stale database metadata and storage totals. Use CTSMD's Delete from storage where possible.

**Verification pending:** Staff download of active/archived originals, CSV export contents, quota and PHP streaming behavior, and denied access for non-staff accounts on real Bluehost hosting.

## Upload progress UX (October 27, 2026)

The staff upload form at `/videos` now sends multipart uploads via `XMLHttpRequest` rather than a silent full-page form submission. It shows actual browser-to-Bluehost transfer progress (percent, transferred bytes, measured average rate, and approximate remaining time). Once the bytes have transferred, the UI changes to **Transfer complete — saving video** and waits for the server to confirm database/file success; 100% transfer is not presented as a successful upload. A completed server response shows **Video saved successfully** and refreshes the library to display the new video.

The form disables edits to avoid duplicate sends while in flight, provides **Cancel upload**, restores the form after a failed upload, and shows actionable messages for rejected files, network errors, upload limits and timeouts. Server-side CSRF, permission, upload-type and size checks remain authoritative. Without JavaScript the standard POST-and-flash flow remains supported.

This does not introduce chunked or resumable uploads. Cancelling or closing the browser while the server is finalizing may not undo processing already underway; check the library before retrying an uncertain upload. The transfer percentage is client-reported and does not measure Bluehost's remaining processing time. Test this specifically with realistic ~50–200 MB files and a deliberately slow connection.
