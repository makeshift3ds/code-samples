import Ember from 'ember';
import ValidateFocusOut from 'employee-portal-cli/components/validate-focus-out';
const {inject: {service}, run: {debounce, cancel, once}, observer} = Ember;

/**
 * Validated iccid is component that validates the length of the iccid
 * and checks it for luhn compatability.
 *
 * @class ValidatedIccid
 * @namespace Components
 */
export default ValidateFocusOut.extend({
  /**
   * i18n service that we can use for translations.
   *
   * @property i18n
   * @type {Ember.Service}
   */
  i18n: service(),

  /**
   * Used for `iccid` validation.
   *
   * @property carrier
   * @type     {DS.Model}
   * @default  null
   */
  carrier: null,

  /**
   * input class that is used for testing
   *
   * @property inputClass
   * @type     {String}
   * @default  js-iccid
   */
  inputClass: 'js-iccid',

  /**
   * check the iccid for luhn validity
   *
   * http://jsfiddle.net/webguy/bLL1cbsq/
   *
   * @method checkLuhn
   * @param  value
   * @return {Boolean}
   */
  checkLuhn(value) {
    return /^\d+$/.test(value) && (value.split('').reverse().reduce((sum, d, n) => {
      return +sum + ((n%2) ? [0,2,4,6,8,1,3,5,7,9][+d] : +d);
    }, 0)) % 10 === 0;
  },

  /**
   * validate the iccid on focusOut
   *
   * @method focusOut
   */
  validationMethod() {
    // reset properties defaults
    this.setProperties({
      error: null,
      success: false
    });

    let val = this.get('value');

    // iccid number is required if gsm
    if (!val) {
      if (this.get('carrier.isGSM')) {
        this.set('error', this.get('i18n').t('tools.validateDevice.errors.iccidRequiredWithKey'));
      } else {
        this.set('success', true);
      }
      return;
    }

    // 19 characters is a valid iccid length, it is just missing the lund check value
    if(val.length === 19) {
      this.set('success', true);
      return;
    }

    // 20 characters is a valid iccid length, check it for lund validity
    if(val.length === 20) {
      if(!this.checkLuhn(val)) {
        this.set('error', this.get('i18n').t('tools.validateDevice.errors.iccidNotValidWithKey'));
      } else {
        this.set('success', true);
      }
    } else {
      this.set('error', this.get('i18n').t('tools.validateDevice.errors.iccidShortWithKey'));
    }
  },

  /**
   * run validationMethod automatically when the value length is 19 or 20.
   * long debounce rate so the user has time to input the luhn checksum before
   * validation begins.
   *
   * @property valueObserver
   */
  valueObserver: observer('value', function() {
    if([19, 20].indexOf(this.get('value.length')) > -1) {
      this.valueObserverDebounce = debounce(this, 'validationMethod', 500);
    }
  }),

  /**
   * Revalidate when carrier changes.
   *
   * @method carrierObserver
   */
  carrierObserver: observer('carrier.id', function() {
    once(this, this.validationMethod);
  }),

  /**
   * Tear down the listeners and timers we set up in this component.
   *
   * @method willDestroyElement
   */
  willDestroyElement() {
    cancel(this.valueObserverDebounce);
    return this._super(...arguments);
  }
});