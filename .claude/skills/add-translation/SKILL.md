---
name: add-translation
description: Add or update translation keys for the Workoflow platform. Use when the user says "add translation", "translate", "add a label", "i18n", "internationalize", or when creating/modifying UI that needs translated text. Also triggers when editing templates that use the |trans filter.
---

# Add Translations

## Setup

- **Format:** YAML with nested dot-notation keys
- **Domain:** Single domain `messages` (no other domains)
- **Fallback:** English (`en`) — missing keys in other locales fall back to English
- **Config:** `config/packages/translation.yaml`

## Locale files (ALL 4 must be updated)

| Locale | File |
|--------|------|
| English | `translations/en/messages.en.yaml` |
| German | `translations/de/messages.de.yaml` |
| Lithuanian | `translations/lt/messages.lt.yaml` |
| Romanian | `translations/ro/messages.ro.yaml` |

**Every new key MUST be added to all 4 files.** The structure must be identical across all files.

## Adding a new key

### 1. Choose the right namespace

Use existing namespaces — don't create new ones unless needed:

| Namespace | Usage |
|-----------|-------|
| `common` | Generic buttons/labels (Save, Cancel, Close) |
| `nav` | Navigation labels |
| `auth` | Login/authentication |
| `general` | Dashboard, agent overview |
| `integration` | Skills/integrations UI |
| `kb` | Knowledge base |
| `scheduled_task` | Scheduled tasks |
| `prompt` | Prompt Vault |
| `profile` | User profile |
| `validation` | Validation errors |
| `api` | API documentation |
| `search` | Search UI |
| `sitemap` | Sitemap crawling |

### 2. Add to all 4 locale files

```yaml
# translations/en/messages.en.yaml
integration:
  my_new_feature:
    title: My New Feature
    description: This feature does something useful
    button_save: Save Changes

# translations/de/messages.de.yaml
integration:
  my_new_feature:
    title: Meine neue Funktion
    description: Diese Funktion macht etwas Nützliches
    button_save: Änderungen speichern

# translations/lt/messages.lt.yaml
integration:
  my_new_feature:
    title: Mano nauja funkcija
    description: Ši funkcija daro kažką naudingo
    button_save: Išsaugoti pakeitimus

# translations/ro/messages.ro.yaml
integration:
  my_new_feature:
    title: Funcția mea nouă
    description: Această funcție face ceva util
    button_save: Salvează modificările
```

### 3. Use in templates (Twig)

```twig
{# Simple key #}
{{ 'integration.my_new_feature.title'|trans }}

{# With parameters #}
{{ 'general.welcome'|trans({'%name%': user.name}) }}

{# In attributes #}
<input placeholder="{{ 'integration.search_skills'|trans }}">
```

### 4. Use in PHP code

```php
// Inject TranslatorInterface
public function __construct(private TranslatorInterface $translator) {}

// Simple
$this->translator->trans('integration.my_new_feature.title');

// With parameters
$this->translator->trans('general.welcome', ['%name%' => $user->getName()]);

// Flash messages
$this->addFlash('success', $this->translator->trans('prompt.created.success'));
```

## Parameters

Use `%placeholder%` syntax (Symfony convention):

```yaml
footer:
  copyright: "© %year% Workoflow. All rights reserved."
general:
  agent_ready: "Your AI Agent has %count% active skill(s)."
search:
  showing_results: "Showing %from% to %to% of %total% entries"
```

**No ICU format** — the project uses simple `%placeholder%` substitution, not `{count, plural, ...}`.

## Checklist

1. Key added to **all 4 locale files** with identical structure
2. Translations are **natural** for each language (not machine-translated gibberish)
3. Parameters use `%name%` syntax consistently
4. No duplicate keys (check existing keys in the namespace first)
5. Run `docker-compose exec frankenphp php bin/console cache:clear` after changes
