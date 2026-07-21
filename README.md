# ani-nya/flarum-i18n

Auto translate discussions for Flarum 2.x. Translates post titles, content, and UI elements using multiple translation engines. Flarum basic elements is not translated due to we have community launage packs.

## Features

- **Auto-translate posts** — posts are translated
- **mutiple translation engines** — choose the one that fits your needs, Google, yandex, Baidu, Deepl, LLM (Openai form) is okay.
- **Multi-language support** — auto-detects target language from user's forum locale
- **Homepage title translation** — discussion list titles are translated
- **Shoutbox translation** — third-party shoutbox widget messages are translated
- **User profile page** — posts on user profiles are translated
- **Original text preserved** — translated content shown with original text below

## Supported Engines

| Engine | API Key Required | Free Tier | Notes |
|--------|:---:|:---:|-------|
| **OpenAI / DeepSeek** | Yes | No | Best quality, supports custom prompts and base URL |
| **DeepL** | Yes | Yes (Free API) | High quality, auto-detects Free vs Pro from key suffix |
| **Google Cloud Translate** | Yes | Yes (500K chars/mo) | V2 API, reliable |
| **Free Google Translate** | No | Yes | No key needed, rate limited (~100 req/min) |
| **Yandex Translate** | Yes | Yes (1M chars/mo) | Cloud API |
| **Baidu Translate** | Yes | Yes (50K chars/mo) | Requires AppID + Secret |
| **LibreTranslate** | Optional | Yes (self-hosted) | Open source, supports custom instances |

I tested OAI Deepl Google Free-Google Yandex and Baidu
Libre is not tested due to is costs expensive. 

## Requirements

- PHP 8.3+
- Flarum 2.0.0-beta+

## Installation

```bash
composer require ani-nya/flarum-i18n
```

Then enable the extension in the Flarum admin dashboard.

## Configuration

1. Go to Admin → Extensions → Auto Translate
2. Enable auto-translation
3. Select your preferred translation engine
4. Fill in the API key / credentials for the selected engine
5. Save settings

Target language is **auto-detected** from each user's language preference (forum language dropdown).

## Engine Setup Guides

### OpenAI / DeepSeek
- **API Key**: Your OpenAI or compatible API key
- **Model**: Default `gpt-4o-mini`, or use DeepSeek (`deepseek-chat`), etc.
- **Base URL**: Default `https://api.openai.com/v1`. Change for compatible providers (e.g., `https://api.deepseek.com` for DeepSeek)
- **Prompt**: Customize translation instructions

### DeepL
- **API Key**: Get from [DeepL](https://www.deepl.com/pro-api). Free API keys end with `:fx`
- Free and Pro endpoints are auto-detected from the key

### Google Cloud Translate
- **API Key**: Create at [Google Cloud Console](https://console.cloud.google.com/) → APIs & Services → Credentials → Create API Key
- Enable "Cloud Translation API" first
- Free tier: 500,000 characters/month

### Free Google Translate
- **No configuration needed**
- Uses Google Translate internal API (free, no key required)
- Rate limited (~100 requests/min)
- Best for low-traffic forums or testing

### Yandex Translate
- **API Key**: Get from [Yandex Cloud Console](https://console.yandex.cloud/) → API Keys
- Enable Yandex Translate service first
- Free tier: 1,000,000 characters/month

### Baidu Translate
- **App ID** + **Secret**: Get from [Baidu Fanyi API](https://fanyi-api.baidu.com/) → Developer Console
- Free tier: 50,000 characters/month (standard)
- Note: Uses Baidu-specific language codes internally (e.g., `jp` for Japanese, `kor` for Korean)

### LibreTranslate
- **URL**: Default `https://libretranslate.com` (public instance)
- Change URL for self-hosted instances
- **API Key**: Optional for public instance, may be required for self-hosted

## How It Works

1. **Translation is triggered** when:
   - A new discussion is created (auto-translate)
   - A user views a discussion page
   - A user views a profile page with posts
   - Homepage is loaded (title translation)

2. **Translations are cached** in the `i18n_translations` database table
   - Repeated views don't consume API quota
   - Post edits trigger re-translation automatically

3. **Frontend rendering**:
   - Discussion pages: VDOM injection (Mithril native)
   - Homepage titles: DOM injection
   - User profiles: VDOM injection
   - Shoutbox: DOM injection with periodic polling

## API Endpoints

### POST `/api/i18n/translate`

Translate all content in a discussion.

```json
{
  "data": {
    "type": "i18n-translations",
    "attributes": {
      "discussion_id": 1,
      "target_lang": "ja"
    }
  }
}
```

**Parameters:**
- `discussion_id` (required): Discussion to translate
- `target_lang` (optional): Target language code (auto-detected if omitted)

### POST `/api/i18n/translate` with `raw_text`

Translate arbitrary text (used for Shoutbox):

```json
{
  "data": {
    "type": "i18n-translations",
    "attributes": {
      "raw_text": "Hello world",
      "target_lang": "ja"
    }
  }
}
```

## CLI Commands

```bash
# Translate a specific discussion
php flarum i18n:translate {discussion_id} --lang={lang_code}

# Translate all recent discussions (last 100)
php flarum i18n:translate --lang={lang_code}
```

## Database

Translations are stored in the `i18n_translations` table:

| Column | Type | Description |
|--------|------|-------------|
| `discussion_id` | bigint | Associated discussion |
| `field` | string | `title`, `content`, or `post_{id}` |
| `source_lang` | string | Detected source language |
| `target_lang` | string | Target language code |
| `source_text` | text | Original text |
| `translated_text` | text | Translated text |
| `engine` | string | Engine used (`deepl`, `google`, `baidu`, etc.) |

## Security

- API keys are stored server-side only and never exposed to the frontend
- Translation API requires user authentication
- Admin settings (including keys) require admin access

## License

This project uses a **dual license**:

- **Non-commercial use**: [AGPL-3.0](LICENSE) — Free for personal projects, education, and open source. Modifications must be open-sourced.
- **Commercial use**: Requires a paid license. Contact mark#mail.ani-nya.com (replace # for @ ) for details.

See [LICENSE](LICENSE) for full terms.


## Donate
If you find this extension useful, consider supporting its development:
- **BNB Chain**: `0xEef952A66bd9116236E9C23Ca6f12272FA53c7Ce`
- [爱发电](https://ifdian.net/a/u0_ani-nya)
- looking for my forum [f.ani-nya.com](https://f.ani-nya.com)（ACGN related）
- or donate to charity organizations

