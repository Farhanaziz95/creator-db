-- Caps AI niche-classification retries. Without this, a genuinely
-- unclassifiable profile (empty bio, every model fails every time) stays
-- in the queue forever, quietly consuming a slot in every future batch.
-- Run in phpMyAdmin: select creator_db -> SQL tab -> paste -> Go.

ALTER TABLE ai_queue MODIFY status ENUM('pending', 'processing', 'failed') DEFAULT 'pending';
