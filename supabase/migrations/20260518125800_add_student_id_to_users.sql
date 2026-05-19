-- Migration: Add student_id column to users table
ALTER TABLE users ADD COLUMN student_id VARCHAR(20) NULL;
