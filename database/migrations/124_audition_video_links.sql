-- 124: Optional external audition video URL. No video files or embeds are stored.
-- Staff enable the field per audition; public forms hide it by default.
ALTER TABLE registration_opportunities
  ADD COLUMN allow_audition_video_links TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE registration_submissions
  ADD COLUMN audition_video_url VARCHAR(2048) NULL;
