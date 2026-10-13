CREATE DATABASE event_ticket_platform;

USE event_ticket_platform;

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    venue VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    total_seats INT NOT NULL DEFAULT 100,
    image VARCHAR(500),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    seat_number VARCHAR(20) NOT NULL,
    seat_row VARCHAR(10),
    seat_type ENUM('regular','premium','vip') DEFAULT 'regular',
    price DECIMAL(10,2) NOT NULL,
    status ENUM('available','locked','booked') DEFAULT 'available',

    locked_by VARCHAR(100) NULL,
    locked_until DATETIME NULL,

    UNIQUE(event_id, seat_number),

    FOREIGN KEY(event_id)
        REFERENCES events(id)
        ON DELETE CASCADE
);

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(50) UNIQUE NOT NULL,

    event_id INT NOT NULL,

    customer_name VARCHAR(150) NOT NULL,
    customer_email VARCHAR(200) NOT NULL,
    customer_phone VARCHAR(30),

    total_amount DECIMAL(10,2) NOT NULL,

    status ENUM('pending','confirmed','cancelled')
        DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY(event_id)
        REFERENCES events(id)
        ON DELETE CASCADE
);

CREATE TABLE booking_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,

    booking_id INT NOT NULL,
    seat_id INT NOT NULL,

    price DECIMAL(10,2) NOT NULL,

    FOREIGN KEY(booking_id)
        REFERENCES bookings(id)
        ON DELETE CASCADE,

    FOREIGN KEY(seat_id)
        REFERENCES seats(id)
        ON DELETE CASCADE
);

INSERT INTO events
(title, description, venue, city, event_date, event_time, price, total_seats, image)
VALUES

(
'Bangalore Music Festival',
'An unforgettable evening of live music and entertainment.',
'Palace Grounds',
'Bangalore',
'2026-11-15',
'19:00:00',
799,
120,
'https://images.unsplash.com/photo-1501386761578-eac5c94b800a'
),

(
'Tech Conference 2026',
'Future of AI, Web Development and Technology.',
'Bangalore International Centre',
'Bangalore',
'2026-12-05',
'10:00:00',
1299,
100,
'https://images.unsplash.com/photo-1540575467063-178a50c2df87'
),

(
'Stand Up Comedy Night',
'Laugh your heart out with India's best comedians.',
'Phoenix Mall',
'Bangalore',
'2026-11-25',
'20:00:00',
499,
80,
'https://images.unsplash.com/photo-1585699324551-f6c309eedeca'
);