# ResQLink - Disaster Shelter & Evacuation Management System

## Project Overview
ResQLink is a web-based disaster management system designed to provide real-time coordination, structured evacuation planning, and efficient shelter management during natural disasters.

## Project Structure

```
ResQLink_codes/
│
├── index.html                 # Main homepage
├── css/
│   └── style.css             # Main stylesheet with responsive design
├── js/
│   └── script.js             # JavaScript for interactive features
├── pages/
│   ├── login.html            # User login page
│   └── register.html         # User registration page
└── README.md                 # This file
```

## Features Implemented in Homepage

### 1. Navigation Bar
- Sticky top navigation with ResQLink branding
- Responsive mobile menu
- Quick links to Home, Features, About sections
- Login and Register buttons

### 2. Hero Section
- Eye-catching welcome message
- Call-to-action buttons
- Animated background with floating effect
- Gradient background with responsive typography

### 3. Features Section
- 8 feature cards showcasing system capabilities
  - Real-Time Alerts
  - Shelter Management
  - Track Status
  - Rescue Coordination
  - Dashboard
  - Mobile Responsive
  - Secure
  - Reports
- Hover animations and transitions
- Responsive grid layout

### 4. About Section
- System overview and purpose
- Problem statement addressing
- Key benefits listing
- Responsive two-column layout

### 5. User Types Section
- Visual cards for each user role
  - Citizens
  - Administrators
  - Rescue Teams
  - Government Bodies
  - System Administrators
  - All Users

### 6. Call-to-Action Section
- Prominent registration and login buttons
- Red background with white text
- Responsive button layout

### 7. Footer
- Quick links
- Contact information
- Copyright notice
- Dark theme styling

## Technologies Used

### Frontend
- **HTML5**: Semantic markup
- **CSS3**: Advanced styling with animations
- **JavaScript**: Interactive features
- **Bootstrap 5**: Responsive design framework
- **Font Awesome 6**: Icon library

### Responsive Design
- Mobile-first approach
- Breakpoints for tablet and desktop
- Flexible grid system
- Touch-friendly buttons

## File Descriptions

### index.html
The main homepage featuring:
- Navigation with smooth scrolling
- Hero section with animated background
- Feature showcase
- About section
- User types information
- Call-to-action sections
- Footer with links

### css/style.css
Complete stylesheet including:
- Root color variables
- Global styles
- Navigation styling
- Hero section animations
- Feature card styling with hover effects
- About section layout
- User card styling
- CTA section
- Footer styling
- Responsive breakpoints
- Scrollbar customization

### js/script.js
Interactive JavaScript features:
- Page initialization
- Active navigation link tracking
- Scroll animations
- Button hover effects
- Smooth scrolling for anchor links
- Mobile menu auto-close
- Ripple effect on buttons
- Scroll-to-top button
- Intersection Observer for animations

### pages/login.html
Login page with:
- Email/Phone number field
- Password field
- Remember me checkbox
- Forgot password link
- Responsive login form
- Link to registration page

### pages/register.html
Registration page with:
- First and Last name fields
- Email and Phone number fields
- Address field
- User role selection (Citizen, Rescue Team, Admin)
- Password and confirm password fields
- Terms and conditions checkbox
- Responsive registration form

## How to Use

### Setup Instructions
1. Extract all files maintaining the folder structure
2. Place in your web server (Apache/XAMPP)
3. Open `index.html` in a web browser

### Local Development
1. Open the project folder in your code editor
2. Use Live Server or local Apache server
3. Navigate to `http://localhost/ResQLink_codes/`

## Styling Features

### Colors
- **Primary Red**: #dc3545 (alerts, buttons, highlights)
- **Dark Gray**: #343a40 (navigation, footer)
- **Light Gray**: #f8f9fa (backgrounds)
- **Text Dark**: #333333 (main text)

### Animations
- Fade-in animations on scroll
- Hover effects on cards and buttons
- Smooth transitions
- Button elevation on hover
- Floating background animation in hero section

### Responsive Breakpoints
- **Mobile**: < 768px
- **Tablet**: 768px - 991px
- **Desktop**: > 992px

## Browser Compatibility
- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile browsers

## Next Steps for Development

### Backend Integration
- PHP page for login/authentication
- Database connection for users
- Session management

### Additional Pages
- User dashboard
- Shelter listing and details
- Alert management
- Profile management
- Admin panel

### Features to Implement
- Database schema
- User authentication system
- Real-time notifications
- API endpoints
- Form validation
- File upload capability

## Important Notes

1. **External CDN Resources**
   - Bootstrap 5 CSS and JS from CDN
   - Font Awesome icons from CDN
   - Ensure internet connection for proper styling

2. **Forms**
   - Currently forms are placeholder only
   - Need backend PHP integration
   - Form validation should be added

3. **Navigation**
   - All internal links are working
   - Ensure proper file paths when moving files

## Performance Metrics
- Fast loading with CSS animations
- Optimized images and icons
- Lazy loading ready for future images
- Smooth scrolling behavior
- Mobile-optimized

## Security Considerations
- Placeholder forms only
- Need HTTPS for production
- Input validation required
- Password encryption needed
- CSRF protection required

## Credits
- Bootstrap Framework
- Font Awesome Icons
- HTML5/CSS3/JavaScript

---

**Version**: 1.0.0  
**Last Updated**: March 2026  
**Status**: Homepage Complete - Ready for Backend Development