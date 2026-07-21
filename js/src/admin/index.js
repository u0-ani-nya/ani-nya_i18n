import Extend from 'flarum/common/extenders';
import TranslationSettingsPage from './components/I18nSettingsPage';

export default [
  new Extend.Admin().page(TranslationSettingsPage),
];
