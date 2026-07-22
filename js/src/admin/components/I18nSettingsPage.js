import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';

class TranslationSettingsPage extends ExtensionPage {
  oninit(vnode) {
    super.oninit(vnode);
    this.discussionId = '';
    this.loading = false;
    this.message = '';
  }

  callAdmin(action, body = {}) {
    this.loading = true;
    this.message = '';
    m.redraw();

    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + action,
      body,
    }).then((result) => {
      this.loading = false;
      this.message = JSON.stringify(result.data);
      m.redraw();
    }).catch((e) => {
      this.loading = false;
      this.message = 'Error: ' + e.message;
      m.redraw();
    });
  }

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

        this.submitButton(),

        m('hr'),

        m('h3', null, 'Manage Translations'),

        m('div', { className: 'I18nAdminActions' },
          m('div', { className: 'I18nAdminRow' },
            Button.component({
              className: 'Button Button--primary',
              loading: this.loading,
              onclick: () => this.callAdmin('/i18n/admin/clear-all'),
            }, 'Clear All Translations'),
            Button.component({
              className: 'Button Button--primary',
              loading: this.loading,
              onclick: () => this.callAdmin('/i18n/admin/fill-all'),
            }, 'Fill Missing Translations')
          ),
          m('div', { className: 'I18nAdminRow' },
            m('input', {
              className: 'FormControl',
              type: 'number',
              placeholder: 'Discussion ID',
              value: this.discussionId,
              oninput: (e) => { this.discussionId = e.target.value; },
            }),
            Button.component({
              className: 'Button Button--danger',
              loading: this.loading,
              disabled: !this.discussionId,
              onclick: () => this.callAdmin('/i18n/admin/clear-discussion', { discussion_id: Number(this.discussionId) }),
            }, 'Clear'),
            Button.component({
              className: 'Button Button--primary',
              loading: this.loading,
              disabled: !this.discussionId,
              onclick: () => this.callAdmin('/i18n/admin/fill-discussion', { discussion_id: Number(this.discussionId) }),
            }, 'Fill')
          ),
          this.message ? m('div', { className: 'I18nAdminMessage' }, this.message) : null
        ),

        m('hr'),

        m('div', { className: 'I18nCopyright' },
          m('p', null, 'Plugin developed by ', m('a', { href: 'https://f.ani-nya.com', target: '_blank' }, 'https://f.ani-nya.com')),
          m('p', null, 'BNB Chain: ', m('code', null, '0xEef952A66bd9116236E9C23Ca6f12272FA53c7Ce'))
        )
      )
    );
  }
}

export default TranslationSettingsPage;
