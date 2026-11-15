-- Starter content. Everything here is editable from the admin panel.
-- NOTE: experience and testimonials are SAMPLE data - replace them before going live.

INSERT INTO settings (skey, svalue) VALUES
('owner_name', 'Elrich Madrileno'),
('logo_text', 'DEV.ELRICH'),
('nav_cta', 'Let''s Talk'),
('footer_text', '© {year} Elrich Madrileno. All rights reserved.'),
('privacy_url', ''),
('terms_url', ''),
('show_services', '1'),
('show_projects', '1'),
('show_experience', '1'),
('show_testimonials', '1'),
('sample_notice', '1'),
('hero_badge', 'Available for new opportunities'),
('hero_title', 'I Build Modern
Web **Experiences**'),
('hero_subtitle', 'Full-stack developer crafting beautiful, fast and scalable digital products that solve real-world problems.'),
('hero_image', ''),
('stat1_value', '3+'),
('stat1_label', 'Years Experience'),
('stat2_value', '20+'),
('stat2_label', 'Projects Completed'),
('stat3_value', '10+'),
('stat3_label', 'Happy Clients'),
('about_title', 'Building solutions
with code & **creativity.**'),
('about_text', 'I''m a passionate Full-Stack Developer who loves building web applications and digital products. I enjoy turning ideas into reality through clean code and user-centered design.'),
('about_points', 'Problem solver by nature
Clean, scalable and maintainable code
Passionate about performance & UX'),
('about_image', ''),
('about_signature', 'Elrich'),
('cv_file', ''),
('skills_note', 'Always learning new technologies'),
('contact_title', 'Let''s build something
**amazing** together.'),
('contact_text', 'Have a project in mind or want to say hello? I''d love to hear from you.'),
('contact_email', 'hello@example.com'),
('contact_phone', '+63 900 000 0000'),
('contact_location', 'Iloilo City, Philippines'),
('contact_availability', 'Mon - Fri (9AM - 6PM PHT)'),
('meta_title', 'Elrich Madrileno | Full-Stack Web Developer'),
('meta_description', 'Portfolio of Elrich Madrileno, a full-stack developer building modern, fast and scalable web experiences.'),
('og_image', '');

INSERT INTO skills (name, icon_class, sort_order) VALUES
('JavaScript', 'devicon-javascript-plain colored', 1),
('TypeScript', 'devicon-typescript-plain colored', 2),
('React', 'devicon-react-original colored', 3),
('Next.js', 'devicon-nextjs-plain', 4),
('Node.js', 'devicon-nodejs-plain colored', 5),
('HTML5', 'devicon-html5-plain colored', 6),
('CSS3', 'devicon-css3-plain colored', 7),
('Tailwind CSS', 'devicon-tailwindcss-original colored', 8),
('PHP', 'devicon-php-plain colored', 9),
('MySQL', 'devicon-mysql-original colored', 10),
('MongoDB', 'devicon-mongodb-plain colored', 11),
('Git', 'devicon-git-plain colored', 12),
('Docker', 'devicon-docker-plain colored', 13),
('Figma', 'devicon-figma-plain colored', 14),
('GraphQL', 'devicon-graphql-plain colored', 15);

INSERT INTO services (title, description, icon, sort_order) VALUES
('Web Development', 'Building responsive, performant and modern websites & web applications.', 'fa-solid fa-code', 1),
('UI/UX Design', 'Designing intuitive and beautiful interfaces that provide exceptional user experiences.', 'fa-solid fa-pen-ruler', 2),
('API Development', 'Building robust, scalable and secure APIs to power your digital products.', 'fa-solid fa-cubes', 3),
('Performance Optimization', 'Improving speed, SEO and overall performance for a better user experience.', 'fa-solid fa-gauge-high', 4);

INSERT INTO projects (title, description, tags, live_url, repo_url, is_featured, sort_order) VALUES
('Analytics Dashboard', 'Real-time analytics dashboard with interactive charts and advanced filtering.', 'React, Node.js, MongoDB', '#', '', 1, 1),
('SaaS Landing Page', 'Modern and conversion-focused landing page for a SaaS product.', 'Next.js, TypeScript, Tailwind', '#', '', 1, 2),
('E-commerce Platform', 'Full-featured e-commerce platform with payment integration and admin panel.', 'PHP, MySQL, Stripe', '#', '', 1, 3);

INSERT INTO experiences (role, company, period, description, icon, sort_order) VALUES
('Full Stack Developer', 'Freelance', '2024 - Present', 'Building web apps and websites for clients end-to-end, from design to deployment.', 'fa-solid fa-laptop-code', 1),
('Web Developer', 'Sample Company', '2023 - 2024', 'Built and maintained multiple client projects using modern web stacks.', 'fa-solid fa-layer-group', 2),
('Frontend Developer', 'Sample Studio', '2022 - 2023', 'Developed responsive websites and web apps using modern frameworks.', 'fa-solid fa-display', 3),
('IT Student', 'West Visayas State University', '2022 - Present', 'Studying information technology and collaborating on academic and personal projects.', 'fa-solid fa-graduation-cap', 4);

INSERT INTO testimonials (name, position, quote, rating, sort_order) VALUES
('Sample Client', 'Product Manager, Company', 'Replace this sample testimonial from the admin panel with real feedback from a client or teammate.', 5, 1),
('Sample Client', 'CEO, Company', 'Working together was a fantastic experience. Understood our requirements perfectly and built a product that exceeded our expectations.', 5, 2),
('Sample Client', 'CTO, Company', 'Code quality, communication and dedication are outstanding. Highly recommended for any project.', 5, 3);

INSERT INTO socials (platform, icon, url, sort_order) VALUES
('GitHub', 'fa-brands fa-github', 'https://github.com/', 1),
('LinkedIn', 'fa-brands fa-linkedin-in', 'https://www.linkedin.com/', 2),
('X / Twitter', 'fa-brands fa-x-twitter', 'https://x.com/', 3),
('Instagram', 'fa-brands fa-instagram', 'https://www.instagram.com/', 4),
('Dribbble', 'fa-brands fa-dribbble', 'https://dribbble.com/', 5);
