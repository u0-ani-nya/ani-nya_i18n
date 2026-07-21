import ExtensionPage from 'flarum/admin/components/ExtensionPage';

class TranslationSettingsPage extends ExtensionPage {
  content() {
    return m('div', { className: 'I18nSettingsPage' },
      m('div', { className: 'container' },
        m('h2', null, 'Auto Translate Settings'),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.auto_translate',
          label: 'Enable Auto Translation',
          type: 'switch',
        }),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.engine',
          label: 'Translation Engine',
          type: 'select',
          options: {
            openai: 'OpenAI',
            deepl: 'DeepL',
            google: 'Google Cloud Translate',
            yandex: 'Yandex Translate',
            baidu: 'Baidu Translate',
            libre: 'LibreTranslate',
            free_google: 'Free Google Translate',
          },
        }),

        m('div', { className: 'helpText' },
          'Target language is auto-detected from each user\'s language preference (forum language dropdown).'
        ),

        m('hr'),

        m('h3', null, 'OpenAI'),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.openai_key',
          label: 'OpenAI API Key',
          type: 'text',
        }),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.openai_model',
          label: 'OpenAI Model',
          type: 'text',
        }),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.openai_base_url',
          label: 'OpenAI Base URL',
          type: 'text',
        }),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.openai_prompt',
          label: 'Translation Prompt',
          type: 'textarea',
          help: 'Use {target_lang} for target language, {text} for the text to translate',
        }),

        m('h3', null, 'Google Cloud Translate'),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.google_key',
          label: 'Google API Key',
          type: 'text',
        }),

        m('h3', null, 'DeepL'),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.deepl_key',
          label: 'DeepL API Key',
          type: 'text',
        }),

        m('h3', null, 'Yandex Translate'),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.yandex_key',
          label: 'Yandex API Key',
          type: 'text',
        }),

        m('h3', null, 'Baidu Translate'),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.baidu_appid',
          label: 'App ID',
          type: 'text',
        }),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.baidu_secret',
          label: 'Secret Key',
          type: 'text',
        }),

        m('h3', null, 'LibreTranslate'),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.libre_url',
          label: 'LibreTranslate URL',
          type: 'text',
          help: 'Default: https://libretranslate.com',
        }),

        this.buildSettingComponent({
          setting: 'ani-nya-i18n.libre_key',
          label: 'API Key (optional)',
          type: 'text',
          help: 'Required for managed instances, optional for self-hosted',
        }),

        m('h3', null, 'Free Google Translate'),

        m('div', { className: 'helpText' },
          'No configuration needed. Uses Google Translate internal API (free, no key required). Rate limited (~100 req/min).'
        ),

        this.submitButton()
      )
    );
  }
}

export default TranslationSettingsPage;
