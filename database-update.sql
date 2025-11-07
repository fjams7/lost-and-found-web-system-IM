-- Database Update for Image Support
-- Run this SQL to add image support to existing database

USE lostfound_hub;

-- Add image_path column to items table if it doesn't exist
ALTER TABLE items 
ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL AFTER photo_url;

-- Create uploads directory structure (to be created manually)
-- /workspace/uploads/lost-and-found-basic/uploads/items/