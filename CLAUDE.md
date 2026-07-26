# Tailor Manager – Measurement, Orders & Delivery — CLAUDE.md

## WordPress Plugin Development Rules

### Project
Professional WordPress plugin. Always follow WordPress Coding Standards.

### Tech Stack
- PHP 8+
- WordPress
- JavaScript (ES6)
- jQuery
- HTML
- CSS

### Coding Rules
- Use OOP.
- Never edit vendor files.
- Escape all output.
- Sanitize all user input.
- Validate all data.
- Use WordPress hooks whenever possible.
- Use AJAX through admin-ajax.php.
- Keep functions small.
- Write reusable code.

### CSS Rules
- Use BEM naming.
- Avoid !important.
- Mobile first.
- Use CSS variables where appropriate.

### JavaScript Rules
- Use jQuery.
- No inline scripts.
- Use event delegation.
- Write modular code.

### UI
- WordPress admin style.
- Clean spacing.
- Rounded corners.
- Professional SaaS appearance.

### Before Writing Code
Always:
1. Analyze the task.
2. Create a plan.
3. Explain the approach.
4. Wait for approval.
5. Then write code.

Never modify unrelated files.
Never break backward compatibility.
Always explain why changes are made.

## Build/Test Commands
- No build step (vanilla PHP/JS/CSS)
- Test: `php -l` on PHP files to check syntax
