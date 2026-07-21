import Model from 'flarum/common/Model';

export default class TranslationRecord extends Model {
  discussionId() {
    return Model.attribute('discussion_id').call(this);
  }
  field() {
    return Model.attribute('field').call(this);
  }
  sourceLang() {
    return Model.attribute('source_lang').call(this);
  }
  targetLang() {
    return Model.attribute('target_lang').call(this);
  }
  sourceText() {
    return Model.attribute('source_text').call(this);
  }
  translatedText() {
    return Model.attribute('translated_text').call(this);
  }
  engine() {
    return Model.attribute('engine').call(this);
  }
}
