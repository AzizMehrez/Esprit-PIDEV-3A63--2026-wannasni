-- Insert test participation data for feedback testing
-- Make sure to replace the user ID with your actual logged-in user ID

-- First, check your user ID by running: SELECT id, email FROM user;
-- Then replace USER_ID_HERE with your actual user id

-- Insert sample participations
INSERT INTO participation (activity_id, senior_id, status, registered_at, registration_method, title) VALUES
(1, USER_ID_HERE, 'présent', NOW() - INTERVAL 5 DAY, 'web', 'Morning Walk'),
(2, USER_ID_HERE, 'inscrit', NOW() - INTERVAL 3 DAY, 'web', 'Memory Games'),
(3, USER_ID_HERE, 'présent', NOW() - INTERVAL 10 DAY, 'web', 'Yoga Class');

-- Verify the inserts
SELECT * FROM participation WHERE senior_id = USER_ID_HERE;
