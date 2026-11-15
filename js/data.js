/* =====================================================================
   data.js — ALL OF YOUR CONTENT LIVES HERE
   ---------------------------------------------------------------------
   Edit it with admin.html (recommended) or by hand.
   Keep the commas between { … } blocks if you edit by hand.
   ===================================================================== */

/* ---------------------------------------------------------------------
   1. SITE / CONTACT INFO
   --------------------------------------------------------------------- */
const SITE = {
  name: "Elrich Jie T. Madrileño",
  brand: "lxrich Visual",
  email: "your.email@example.com",
  phone: "+63 900 000 0000",
  location: "Himamaylan City, Negros Occidental, PH",
  resume: "assets/resume.pdf",
  formspreeId: "FORM_ID",
  roles: ["Web Developer", "AI Enthusiast", "Photographer", "Drone Pilot", "Filmmaker"],
  socials: {
    instagram: "https://www.instagram.com/lxrichh/",
    facebook: "https://www.facebook.com/elrich.jie.madrileno.2025",
    github: "https://github.com/Kinglong05",
    linkedin: "",
    youtube: ""
  }
};

/* ---------------------------------------------------------------------
   2. ABOUT — animated counters
   --------------------------------------------------------------------- */
const STATS = [
  { label: "Photoshoots", value: 50, suffix: "+" },
  { label: "Projects Built", value: 6, suffix: "+" },
  { label: "Short Films", value: 1, suffix: "+" },
  { label: "Certifications", value: 1, suffix: "" }
];

/* ---------------------------------------------------------------------
   3. SKILLS
   level: 0–100 (bar %)  ·  icon: a Devicon class (https://devicon.dev) or any emoji
   --------------------------------------------------------------------- */
const SKILLS = [
  {
    category: "Programming",
    items: [
      { name: "HTML", icon: "devicon-html5-plain colored", level: 90 },
      { name: "CSS", icon: "devicon-css3-plain colored", level: 85 },
      { name: "JavaScript", icon: "devicon-javascript-plain colored", level: 75 },
      { name: "PHP", icon: "devicon-php-plain colored", level: 80 },
      { name: "Python", icon: "devicon-python-plain colored", level: 85 },
      { name: "SQL", icon: "🗃️", level: 80 }
    ]
  },
  {
    category: "Frameworks & Tools",
    items: [
      { name: "MySQL", icon: "devicon-mysql-original colored", level: 80 },
      { name: "XAMPP", icon: "🧰", level: 85 },
      { name: "Tkinter", icon: "🪟", level: 80 },
      { name: "SQLite", icon: "devicon-sqlite-plain colored", level: 75 },
      { name: "Leaflet.js (GIS)", icon: "🗺️", level: 70 },
      { name: "Git / GitHub", icon: "devicon-git-plain colored", level: 75 },
      { name: "VS Code", icon: "devicon-vscode-plain colored", level: 90 }
    ]
  },
  {
    category: "AI / ML",
    items: [
      { name: "YOLOv8 (Ultralytics)", icon: "🎯", level: 80 },
      { name: "PyTorch", icon: "devicon-pytorch-original colored", level: 65 },
      { name: "OpenCV", icon: "devicon-opencv-plain colored", level: 75 },
      { name: "DeepFace", icon: "🙂", level: 70 },
      { name: "RAG", icon: "📚", level: 65 }
    ]
  },
  {
    category: "Other IT",
    items: [
      { name: "Basic Cybersecurity", icon: "🛡️", level: 60 },
      { name: "Computer Troubleshooting", icon: "🔧", level: 85 },
      { name: "System Design", icon: "🧩", level: 75 }
    ]
  },
  {
    category: "Creative",
    items: [
      { name: "Photography", icon: "📷", level: 90 },
      { name: "Videography", icon: "🎥", level: 85 },
      { name: "Video Editing", icon: "🎞️", level: 85 },
      { name: "Drone Piloting (DJI Mini 3)", icon: "🚁", level: 85 },
      { name: "Lightroom Presets", icon: "🎨", level: 80 },
      { name: "Layout / Graphic Design", icon: "✏️", level: 75 },
      { name: "Scriptwriting & Directing", icon: "🎬", level: 75 }
    ]
  },
  {
    category: "Soft Skills",
    items: [
      { name: "Leadership (Senior Scout)", icon: "⚜️", level: 85 },
      { name: "Teamwork", icon: "🤝", level: 90 },
      { name: "Creativity", icon: "💡", level: 90 },
      { name: "Communication", icon: "💬", level: 80 }
    ]
  },
  {
    category: "Languages",
    items: [
      { name: "Hiligaynon", icon: "🗣️", level: 100 },
      { name: "Filipino", icon: "🗣️", level: 90 },
      { name: "English", icon: "🗣️", level: 85 }
    ]
  }
];

/* ---------------------------------------------------------------------
   4. PROJECTS
   categories: "web" | "ai" | "desktop" | "creative"
   video: optional YouTube/Facebook link  ·  github / live: "" hides the button
   --------------------------------------------------------------------- */
const PROJECTS = [
  {
    id: "mangrovision",
    title: "MANGROVISION — Autonomous Mangrove",
    subtitle: "Capstone Thesis",
    categories: ["ai", "web"],
    thumb: "assets/projects/mangrovision.webp",
    summary: "Centralized web-based AI system for DENR-ENRO Himamaylan that identifies mangrove species, detects disease and measures canopy density from drone imagery.",
    description: [
      "A centralized web-based AI system built for DENR-ENRO Himamaylan that identifies mangrove species, detects disease and measures canopy density from drone-captured imagery.",
      "Field teams upload DJI Mini 3 captures, which pass through an image quality gate before YOLOv8 classifies species. Results go into a human-in-the-loop verification queue, so experts confirm every prediction before it reaches official reports."
    ],
    features: [
      "Role-based access control",
      "Drone image quality gate (DJI Mini 3)",
      "YOLOv8 species classification",
      "ExG-based canopy density measurement",
      "Human-in-the-loop verification queue",
      "Leaflet GIS map of survey sites",
      "Statutory report export",
      "RAG-powered assistant"
    ],
    tech: ["PHP", "MySQL", "Python", "YOLOv8", "PyTorch", "Leaflet", "JavaScript", "RAG"],
    screenshots: ["assets/projects/mangrovision-1.webp", "assets/projects/mangrovision-2.webp"],
    github: "https://github.com/",
    live: ""
  },
  {
    id: "face-expression",
    title: "AI Facial Expression Detector",
    categories: ["ai"],
    thumb: "assets/projects/face-expression.webp",
    summary: "Real-time webcam app that detects and labels facial emotions.",
    description: [
      "A real-time computer-vision app that reads a webcam feed, finds faces with OpenCV, and labels each face's dominant emotion using DeepFace."
    ],
    features: ["Live webcam capture", "Multi-face detection", "On-screen emotion labels and confidence"],
    tech: ["Python", "OpenCV", "DeepFace"],
    screenshots: ["assets/projects/face-expression-1.webp"],
    github: "https://github.com/",
    live: ""
  },
  {
    id: "booking-tracker",
    title: "Client Booking & Payment Tracker",
    categories: ["desktop"],
    thumb: "assets/projects/booking-tracker.webp",
    summary: "Desktop app for my photography business to manage clients, shoots and payments.",
    description: [
      "A desktop app built for my own photography business. It tracks clients, scheduled shoots, packages and payment status in one place, and exports records to CSV."
    ],
    features: ["Client and shoot records", "Payment and balance tracking", "CSV export"],
    tech: ["Python", "Tkinter", "SQLite"],
    screenshots: ["assets/projects/booking-tracker-1.webp"],
    github: "https://github.com/",
    live: ""
  },
  {
    id: "scout-records",
    title: "Senior Scout Records System",
    categories: ["desktop"],
    thumb: "assets/projects/scout-records.webp",
    summary: "Records system for my alma mater's Senior Scout outfit, built around official BSP ranks.",
    description: [
      "A records system for my alma mater's Senior Scout outfit. It is built around the official Boy Scouts of the Philippines rank structure, so the outfit adviser can track members and their advancement."
    ],
    features: ["Member profiles", "BSP rank progression tracking", "Adviser-friendly interface"],
    tech: ["Python"],
    screenshots: [],
    github: "https://github.com/",
    live: ""
  },
  {
    id: "hiraya",
    title: "“Hiraya” — Short Film",
    categories: ["creative"],
    thumb: "assets/projects/hiraya.webp",
    summary: "Writer and co-director of a Hiligaynon short film for the WVSU Himamaylan Short Film Competition.",
    description: [
      "I wrote and co-directed a Hiligaynon short film entry for the WVSU Himamaylan Short Film Competition, under the theme HARAYA."
    ],
    features: ["Writer", "Co-director", "Hiligaynon-language production"],
    tech: ["Scriptwriting", "Directing", "Video Editing"],
    video: "",
    screenshots: [],
    github: "",
    live: ""
  },
  {
    id: "logo-intro",
    title: "Logo Intro Animation",
    categories: ["creative"],
    thumb: "assets/projects/logo-intro.webp",
    summary: "A sketch-to-logo animated intro for my lxrich Visual brand.",
    description: [
      "An animated brand intro in which my lxrich Visual logo is sketched line by line before resolving into the final mark. It's the same idea as this site's loading screen."
    ],
    features: ["Sketch-draw reveal", "Brand-colour finish"],
    tech: ["Motion Design", "Video Editing"],
    video: "",
    screenshots: [],
    github: "",
    live: ""
  }
];

/* ---------------------------------------------------------------------
   5. PHOTOGRAPHY & VIDEO GALLERY
   photo: { type: "photo", category: "portraits"|"kids"|"drone"|"events", src, thumb?, w, h, alt, caption, fav? }
   video: { type: "video", video: "<Facebook/YouTube link or assets/videos/x.mp4>", cover?, vertical?, alt, caption, fav? }
   Facebook videos must be Public.
   --------------------------------------------------------------------- */
const GALLERY = [
  {
    type: "photo",
    category: "portraits",
    src: "assets/photos/portrait-1.webp",
    w: 600,
    h: 800,
    alt: "Outdoor portrait in soft natural light",
    caption: "Golden hour portrait",
    fav: true
  },
  {
    type: "photo",
    category: "portraits",
    src: "assets/photos/portrait-2.webp",
    w: 600,
    h: 750,
    alt: "Close-up portrait with shallow depth of field",
    caption: "Portrait session"
  },
  {
    type: "photo",
    category: "portraits",
    src: "assets/photos/portrait-3.webp",
    w: 600,
    h: 400,
    alt: "Environmental portrait in the city",
    caption: "Street portrait"
  },
  {
    type: "photo",
    category: "kids",
    src: "assets/photos/kids-1.webp",
    w: 600,
    h: 750,
    alt: "Birthday party decoration details",
    caption: "1st birthday session",
    fav: true
  },
  {
    type: "photo",
    category: "kids",
    src: "assets/photos/kids-2.webp",
    w: 600,
    h: 400,
    alt: "Colorful dessert table at a kids' party",
    caption: "Party details"
  },
  {
    type: "photo",
    category: "drone",
    src: "assets/photos/drone-1.webp",
    w: 600,
    h: 400,
    alt: "Aerial view of a river winding through a valley",
    caption: "Aerial — DJI Mini 3",
    fav: true
  },
  {
    type: "photo",
    category: "drone",
    src: "assets/photos/drone-2.webp",
    w: 600,
    h: 400,
    alt: "Aerial landscape of mountains and coastline",
    caption: "Coastline from above"
  },
  {
    type: "photo",
    category: "drone",
    src: "assets/photos/drone-3.webp",
    w: 600,
    h: 750,
    alt: "Top-down drone shot of a forest",
    caption: "Canopy survey",
    fav: true
  },
  {
    type: "photo",
    category: "events",
    src: "assets/photos/events-1.webp",
    w: 600,
    h: 800,
    alt: "Event table setup with warm lights",
    caption: "Event coverage"
  },
  {
    type: "photo",
    category: "events",
    src: "assets/photos/events-2.webp",
    w: 600,
    h: 400,
    alt: "Crowd at an outdoor city event",
    caption: "Community event",
    fav: true
  },
  {
    type: "video",
    video: "https://www.facebook.com/facebook/videos/10153231379946729/",
    alt: "Sample Facebook video embed",
    caption: "Facebook video (sample — replace me)"
  },
  {
    type: "video",
    video: "https://www.facebook.com/facebook/videos/10153231379946729/",
    cover: "assets/photos/video-cover-1.webp",
    w: 1280,
    h: 720,
    alt: "Cinematic edit cover",
    caption: "Cinematic edit (sample — replace me)",
    fav: true
  }
];

/* ---------------------------------------------------------------------
   6. EDUCATION & EXPERIENCE (newest first)
   status: "ongoing" | "upcoming" | ""
   --------------------------------------------------------------------- */
const TIMELINE = [
  {
    icon: "🎓",
    title: "Bachelor of Science in Information Technology",
    org: "West Visayas State University — Himamaylan City Campus",
    period: "20XX – Present · 4th Year",
    status: "ongoing",
    description: "Focused on web systems and applied AI. Capstone: MANGROVISION, an AI mangrove monitoring system for DENR-ENRO Himamaylan."
  },
  {
    icon: "💼",
    title: "On-the-Job Training — IT Department",
    org: "DICT Negros Island Region",
    period: "January – May 20XX · 500 hours",
    status: "upcoming",
    description: "500-hour internship with the IT Department of the Department of Information and Communications Technology."
  },
  {
    icon: "📷",
    title: "Freelance Photographer, Videographer & Drone Pilot",
    org: "lxrich Visual (formerly AE Photography)",
    period: "20XX – Present",
    status: "ongoing",
    description: "Portraits, kids' and birthday sessions, events, aerial photography and video edits for clients around Negros Occidental."
  },
  {
    icon: "⚜️",
    title: "Senior Scout",
    org: "Boy Scouts of the Philippines — [Alma Mater]",
    period: "20XX – 20XX",
    status: "",
    description: "Leadership, service and outdoor skills training. Built the outfit's records system."
  },
  {
    icon: "🏫",
    title: "Senior High School",
    org: "[School Name] — [Strand]",
    period: "20XX – 20XX",
    status: "",
    description: ""
  }
];

/* ---------------------------------------------------------------------
   7. CERTIFICATIONS & ACHIEVEMENTS
   link: optional URL to the certificate
   --------------------------------------------------------------------- */
const CERTIFICATIONS = [{ icon: "🪚", title: "Carpentry NC II", issuer: "TESDA", year: "20XX", link: "" }];
