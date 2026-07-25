# Coding Principles

Guidelines for how changes should be approached in this repository.

## The Three Commandments

### I. Simplicity First

- Minimum code. Don't speculate.
- No features beyond what was asked, no abstractions for one-off code, no "flexibility" nobody requested.
- If 200 lines can be 50, rewrite it.
- **Test:** Would a senior engineer call this "complicated"? Simplify.

### II. Surgical Changes

- Touch only what you must. Don't improve neighboring code. Don't refactor what isn't broken.
- Match the existing style. Mention dead code instead of removing it — remove only the orphans you created yourself.
- **Test:** Every change should trace directly back to the requirement.

### III. Goal-Driven Execution

- Define success. Loop until it's met.
- Instead of "Add validation" → **"Invalid-input tests → pass"**
- Instead of "Fix the bug" → **"Test reproduces the bug → pass"**
- Instead of "Refactor X" → **"Tests before and after → pass"**
- For multi-step work, pair each step with a verification:
  1. [Step] → verify: [check]
  2. [Step] → verify: [check]
  3. [Step] → verify: [check]

**Motto: think, simplify, execute precisely.**

---

## Code Quality Rules

### 1. Single Responsibility

- Don't use "and" in a method/class description.
- If "and" shows up, split it into two things.
- A class does one thing. A method does one thing. That's it.

### 2. If-Statements Can Be Evil

- Deeply nested, unreadable conditions ruin code.
- Extract the result of a complex `&&` / `||` into a named variable or a private method.
- Prefer early returns over nesting.
- **Test:** Is the condition understandable in 2 seconds? If not, simplify.

### 3. Thin Controllers

- A controller **takes a request and returns a response**. Nothing more.
- Business logic belongs in a service.
- Validation belongs in a service or a dedicated validator.
- A controller is a router, not a brain.

### 4. SOLID / DRY — Pragmatically

- Apply SOLID and DRY, but without dogma.
- Don't build abstractions "for the future" — build them when a second real need shows up.
- DRY means don't duplicate logic. Two similar `if`s aren't a reason to extract a class.

### 5. Code for Humans

- Must be graspable in a single glance.
- Variable/method names say WHAT they do, not HOW.
- If you need to read it three times to understand it, rewrite it.
- A comment is a naming failure. The exception is explaining "why", never "what".
