import { extend } from 'flarum/common/extend';
import Extend from 'flarum/common/extenders';
import app from 'flarum/forum/app';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import CommentPost from 'flarum/forum/components/CommentPost';
import TranslationRecord from '../common/models/I18nTranslation';

const fetched = {};
const translationCache = {};
const translatedAt = {};
let currentDiscussionId = null;

function doTranslate(discussion) {
  const id = discussion.id();
  if (fetched[id]) return;
  fetched[id] = true;

  const targetLang = app.data?.locale || 'zh-Hans';

  app.request({
    method: 'POST',
    url: app.forum.attribute('apiUrl') + '/i18n/translate',
    body: {
      data: {
        type: 'i18n-translations',
        attributes: {
          discussion_id: Number(id),
          target_lang: targetLang,
        },
      },
    },
  }).then((result) => {
    const attrs = result?.data?.attributes;
    if (!attrs || !Array.isArray(attrs.translations)) return;

    translationCache[id] = {};
    attrs.translations.forEach(t => {
      const key = t.post_id ? 'post_' + t.post_id : t.field;
      translationCache[id][key] = t;
    });

    translatedAt[id] = {};
    document.querySelectorAll('.PostStream-item[data-id]').forEach(el => {
      const postId = el.getAttribute('data-id');
      const post = app.store.getById('posts', postId);
      if (post) {
        const edited = post.editedAt();
        translatedAt[id][postId] = edited ? edited.valueOf() : 0;
        post.__i18nVersion = Date.now();
      }
    });

    document.querySelectorAll('.CommentPost').forEach(el => {
      const container = el.closest('[data-id]');
      if (!container) return;
      const postId = container.getAttribute('data-id');
      const post = app.store.getById('posts', postId);
      if (post && translationCache[id]?.['post_' + postId]) {
        post.__i18nVersion = Date.now();
      }
    });

    if (translationCache[id]?.title) {
      const translated = translationCache[id].title.translated_text;
      discussion.data.attributes.title = translated;

      const heroEl = document.querySelector('.DiscussionHero-title');
      if (heroEl) heroEl.textContent = translated;

      const obs = new MutationObserver(() => {
        const el = document.querySelector('.DiscussionHero-title');
        if (el && el.textContent !== translated) {
          el.textContent = translated;
        }
      });
      const heroContainer = document.querySelector('.DiscussionHero-items');
      if (heroContainer) {
        obs.observe(heroContainer, { childList: true, subtree: true, characterData: true });
      }
      setTimeout(() => obs.disconnect(), 30000);
    }

    m.redraw();
  }).catch(() => {});
}

export default [
  new Extend.Store().add('i18n-translations', TranslationRecord),
];

app.initializers.add('ani-nya-i18n', () => {

  // DiscussionPage：onupdate 触发翻译
  extend(DiscussionPage.prototype, 'onupdate', function () {
    const discussion = this.discussion;
    if (!discussion) return;

    currentDiscussionId = discussion.id();
    const id = discussion.id();

    if (translationCache[id] && translatedAt[id]) {
      let needsRetranslate = false;
      document.querySelectorAll('.PostStream-item[data-id]').forEach(el => {
        const postId = el.getAttribute('data-id');
        const post = app.store.getById('posts', postId);
        if (post && translatedAt[id][postId] !== undefined) {
          const edited = post.editedAt();
          const currentTs = edited ? edited.valueOf() : 0;
          if (currentTs > translatedAt[id][postId]) {
            needsRetranslate = true;
          }
        }
      });
      if (needsRetranslate) {
        delete fetched[id];
        delete translationCache[id];
        delete translatedAt[id];
        document.querySelectorAll('.PostStream-item[data-id]').forEach(el => {
          const post = app.store.getById('posts', el.getAttribute('data-id'));
          if (post) post.__i18nVersion = Date.now();
        });
      }
    }

    doTranslate(discussion);
  });

  // CommentPost subtree.check 注入
  extend(CommentPost.prototype, 'oninit', function () {
    if (!this.subtree) return;
    this.subtree.check(() => this.attrs?.post?.__i18nVersion || 0);
  });

  // CommentPost onbeforeupdate
  extend(CommentPost.prototype, 'onbeforeupdate', function (original) {
    if (!this.attrs?.post) return original;
    const postId = this.attrs.post.id();
    const discId = currentDiscussionId || this.attrs.post.discussion()?.id();
    if (discId && translationCache[discId]?.['post_' + postId]) {
      return true;
    }
    return original;
  });

  // CommentPost content
  extend(CommentPost.prototype, 'content', function (items) {
    if (this.isEditing()) return;

    const post = this.attrs.post;
    if (!post) return;

    const postId = post.id();
    const discussionId = currentDiscussionId || post.discussion()?.id();
    if (!discussionId) return;
    if (!translationCache[discussionId]?.['post_' + postId]) return;

    const t = translationCache[discussionId]['post_' + postId];
    let originalHtml = '';

    items.forEach(function (item) {
      if (item && item.attrs && 'contentHtml' in item.attrs) {
        originalHtml = item.attrs.contentHtml || '';
        item.attrs.contentHtml = t.translated_text;
      }
    });

    items.push(
      <div className="I18n-original"
           style="border-left:3px solid #ccc;padding:8px;margin-top:8px;font-size:0.9em;color:#999">
        <small>原文：</small>
        <div innerHTML={originalHtml} />
      </div>
    );
  });

  // 首页标题翻译
  function translateIndexTitles() {
    document.querySelectorAll('.DiscussionListItem').forEach(item => {
      if (item.dataset.i18nApplied) return;
      const link = item.querySelector('.DiscussionListItem-main');
      if (!link) return;
      const match = link.getAttribute('href')?.match(/\/d\/(\d+)/);
      if (!match) return;
      const discId = match[1];
      if (!translationCache[discId]?.title) return;

      item.dataset.i18nApplied = '1';
      const t = translationCache[discId].title;
      const h2 = item.querySelector('.DiscussionListItem-title');
      if (!h2) return;
      const originalText = h2.textContent;
      if (t.translated_text === originalText) return;
      h2.textContent = t.translated_text;
      const sub = document.createElement('span');
      sub.style.cssText = 'font-size:0.75em;color:#999;display:block;margin-top:2px';
      sub.textContent = originalText;
      h2.parentNode.insertBefore(sub, h2.nextSibling);
    });
  }

  // Shoutbox 翻译
  function translateShoutbox() {
    document.querySelectorAll('.ShoutboxWidget-message-text').forEach(el => {
      if (el.dataset.i18nDone) return;
      const text = el.textContent.trim();
      if (!text || text.length < 2) return;

      el.dataset.i18nDone = '1';
      app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/i18n/translate',
        body: { data: { type: 'i18n-translations', attributes: { raw_text: text, target_lang: app.data?.locale || 'zh-Hans' } } },
      }).then(r => {
        const translated = r?.data?.attributes?.translated_text;
        if (!translated || translated === text) return;
        const original = el.textContent;
        el.textContent = translated;
        const sub = document.createElement('span');
        sub.style.cssText = 'font-size:0.75em;color:#999;display:block;margin-top:2px';
        sub.textContent = original;
        el.parentNode.insertBefore(sub, el.nextSibling);
      }).catch(() => {});
    });
  }

  // 通用翻译触发：监听所有页面，为未翻译的讨论调 API + 应用首页标题
  setTimeout(() => {
    const observer = new MutationObserver(() => {
      const seen = new Set();
      document.querySelectorAll('.CommentPost').forEach(el => {
        const container = el.closest('[data-id]');
        if (!container) return;
        const postId = container.getAttribute('data-id');
        const postModel = app.store.getById('posts', postId);
        if (!postModel) return;
        const discussion = postModel.discussion();
        if (!discussion) return;
        const discId = discussion.id();
        if (discId && !fetched[discId] && !seen.has(discId)) {
          seen.add(discId);
          doTranslate(discussion);
        }
      });
      document.querySelectorAll('.DiscussionListItem').forEach(el => {
        const link = el.querySelector('.DiscussionListItem-main');
        if (!link) return;
        const match = link.getAttribute('href')?.match(/\/d\/(\d+)/);
        if (!match) return;
        const discId = match[1];
        if (fetched[discId] || seen.has(discId)) return;
        const discussion = app.store.getById('discussions', discId);
        if (!discussion) return;
        seen.add(discId);
        doTranslate(discussion);
      });
      translateIndexTitles();
    });
    const main = document.querySelector('#page-main');
    if (main) {
      observer.observe(main, { childList: true, subtree: true });
    }
    setInterval(translateShoutbox, 2000);
  }, 1000);
});
