# AGENTS
## Project Overview
This project is built with Laravel and uses MySQL as the primary database.

The goal of this file is to define engineering conventions and implementation preferences for AI agents working in this repository. Follow these rules unless the user explicitly asks otherwise.

---

## Core Principles
- Prioritize readability, maintainability, and consistency over clever or overly abstract solutions.
- Keep implementations simple and aligned with Laravel best practices.
- Prefer clear separation of concerns.
- Avoid over-engineering.
- Before making changes, understand the surrounding code and follow existing project patterns where reasonable.

---

## Architecture Guidelines

### Controllers
- Controllers must remain thin.
- Controllers should only handle:
  - request input
  - validation coordination
  - calling application services
  - returning responses
- Do not place complex business logic in controllers.

### Services
- Place business logic in service classes.
- Use services for workflows, domain rules, orchestration, and reusable business processes.
- A service should have a clear responsibility.

### Repositories
- Use repositories when needed.
- Repositories are appropriate when:
  - database queries are complex
  - the same data access logic is reused in multiple places
  - data access should be isolated for maintainability or testing
- Do not introduce repositories for trivial CRUD if Eloquent usage is already clear and simple.

### Models
- Keep Eloquent models focused on data representation, relationships, casts, scopes, and simple model-level behavior.
- Do not place large business workflows inside models.
- Prefer explicit relationships, casts, and query scopes where useful.

### Requests / Validation
- Prefer Form Request classes for validation when applicable.
- Keep validation rules out of controllers when they become non-trivial.
- Use custom validation messages only when they improve clarity.

### Actions / Jobs / Events
- Use queued jobs for long-running or asynchronous tasks.
- Use events and listeners when they improve decoupling, not just for the sake of abstraction.
- Keep synchronous request-response flows simple unless asynchronous behavior is clearly needed.

---

## Database Conventions

### General
- Use MySQL-compatible queries and conventions.
- Prefer Laravel Eloquent and Query Builder over raw SQL unless raw SQL is necessary for performance or complexity reasons.
- Keep database access explicit and understandable.

### Migrations
- All schema changes must go through Laravel migrations.
- Do not modify the database structure outside migrations.
- Write reversible migrations whenever possible.
- Keep migrations focused and easy to review.

### Transactions
- Use database transactions when multiple write operations must succeed or fail together.
- Especially use transactions for workflows involving multiple related inserts, updates, or deletes.

### Query Design
- Avoid N+1 query problems.
- Use eager loading when appropriate.
- Select only necessary columns when performance matters.
- Prefer expressive query scopes or repository methods for reused filtering logic.

---

## Coding Standards

### PHP Style
- Follow PSR-12 for all PHP code.
- Follow existing project formatting and linting configuration if present.
- Use meaningful names for classes, methods, variables, and parameters.
- Prefer early returns to reduce nesting.
- Keep methods focused and reasonably short.

### Comments and Documentation
- Do not add unnecessary comments.
- Add PHPDoc or concise comments for:
  - public methods with important business behavior
  - non-obvious logic
  - side effects
  - important assumptions or constraints
- Avoid comments that merely restate the method name.

### Typing
- Use strict and explicit typing where appropriate.
- Add return types and parameter types consistently.
- Prefer typed properties and explicit value handling when supported by the project conventions.

### Error Handling
- Fail clearly and predictably.
- Use framework conventions for exceptions and error responses.
- Do not swallow exceptions silently.
- Log errors where operational visibility is important, but avoid noisy or redundant logging.

---

## Laravel-Specific Preferences

### Laravel Conventions
- Prefer Laravel conventions over custom patterns unless there is a strong reason not to.
- Use dependency injection through the service container.
- Prefer constructor injection for service dependencies.
- Use configuration files and environment variables properly; do not hardcode secrets or environment-specific values.

### Eloquent
- Prefer Eloquent relationships and scopes for common data access patterns.
- Avoid putting too much application logic in model boot methods unless clearly justified.
- Be careful with mass assignment and fillable/guarded behavior.

### Routing
- Keep routes clean and organized.
- Prefer route model binding when it improves clarity.
- Keep route definitions simple and avoid embedding business logic in route closures.

### API Design
- For APIs, prefer consistent response structures.
- Use Resources or transformers when response formatting becomes non-trivial.
- Preserve backward compatibility when modifying existing public endpoints unless the user explicitly requests breaking changes.

### Blade / Views
- Keep views focused on presentation.
- Do not place business logic in Blade templates.
- Prepare data in controllers, view models, or services before rendering.

---

## Testing Expectations
- Add or update tests for meaningful code changes.
- Prefer feature tests for end-to-end application behavior.
- Prefer unit tests for isolated business rules or service logic.
- Test critical business rules, edge cases, and failure scenarios.
- Do not remove tests without a clear reason.
- If a change affects existing behavior, review whether related tests should also be updated.

### Minimum Testing Rule
- New business logic should usually be covered by tests.
- Bug fixes should include a test that would have caught the bug when practical.

---

## File and Code Change Policy
- Make focused changes.
- Do not refactor unrelated parts of the code unless necessary for the requested task.
- Preserve existing architecture unless there is a clear benefit to changing it.
- Follow existing naming, folder structure, and coding patterns used by the repository.

---

## Security and Safety
- Never expose secrets, tokens, passwords, or sensitive configuration values.
- Do not hardcode credentials.
- Validate and sanitize user input appropriately.
- Apply authorization checks where needed.
- Be careful with file uploads, raw queries, dynamic execution, and external integrations.

---

## Performance Guidelines
- Be mindful of query efficiency.
- Avoid unnecessary loops over large datasets.
- Prefer pagination, chunking, or lazy processing where appropriate.
- Consider caching only when it provides clear value and remains maintainable.

---

## When Implementing New Features
Follow this order of thought:
1. Understand the existing flow and project conventions.
2. Identify the correct layer for the change.
3. Keep controllers thin.
4. Place business logic in services.
5. Introduce repositories only if they provide real value.
6. Update migrations if schema changes are required.
7. Add or update tests.
8. Keep the implementation simple, readable, and aligned with Laravel conventions.

---

## When Fixing Bugs
- First identify the root cause.
- Prefer minimal, targeted fixes.
- Check whether the bug is caused by validation, business logic, query logic, data consistency, or edge-case handling.
- Add or update tests for the fix whenever practical.

---

## Preferred Response Behavior for AI Agents
- Before changing code, inspect related files and understand the current implementation.
- Explain significant architectural choices briefly when relevant.
- If requirements are ambiguous, prefer the most conventional Laravel approach.
- If a requested pattern conflicts with existing code style, favor consistency unless the user explicitly requests a new pattern.
- Do not introduce unnecessary packages without clear justification.
- Do not rewrite large sections of code unless required.

---

## Default Folder Responsibilities
Use these conventions unless the repository already defines a different structure:
- app/Http/Controllers: HTTP layer only
- app/Http/Requests: validation logic
- app/Services: business logic and orchestration
- app/Repositories: reusable and non-trivial data access logic
- app/Models: Eloquent models and relationships
- app/Jobs: queued jobs
- app/Events: domain/application events
- app/Listeners: event listeners
- database/migrations: schema changes
- tests/Feature: integration / HTTP behavior
- tests/Unit: isolated logic

---

## Final Rule
When in doubt, prefer:
- Laravel conventions
- thin controllers
- service-based business logic
- repositories only where useful
- PSR-12 compliance
- clear, testable, maintainable code
