import Ember from 'ember'; 
import {translationMacro as t} from 'ember-i18n';
const {Component, computed, inject: {service}, RSVP: {resolve}} = Ember;

/**
 * add wrapper elements and iterate through eventLogs
 * and render each with event-log-item component.
 *
 * @class LiquidAddress
 * @namespace Components
 */
export default Component.extend({
  /**
   * Injects the store service
   *
   * @property store
   * @type     {Ember.Service}
   */
  store: service(),

  /**
   * i18n service that we can use for translations.
   *
   * @property i18n
   * @type {Ember.Service}
   */
  i18n: service(),

  /**
   * This is the host model's address object
   *
   * @property address
   * @type     {DS.Model}
   * @default  null
   */
  address: null,

  /**
   * The proxyAddress gets created by the component and is directly
   * modified by the component when editing address fields. After the
   * component switches back to yielding to the host template, the
   * proxyAddress is either set as the model's address, or has it's fields
   * copied to the model's address fields.
   *
   *
   * @property proxyAddress
   * @type     {DS.Model} address record
   * @default  null
   */
  proxyAddress: null,

  /**
   * Used bypass address selection
   *
   * @property skipAddressSelect
   * @type     {Boolean}
   * @default  false
   */
  skipAddressSelect: false,

  /**
   * Represents the default state. Shows the block defined
   * for the component
   *
   * @property showBlock
   * @type     {Boolean}
   * @default  true
   */
  showBlock: true,

  /**
   * Shows the address selection panel
   *
   * @property showAddressSelect
   * @type     {Boolean}
   * @default  false
   */
  showAddressSelect: false,

  /**
   * Shows the form for entering a new address
   *
   * @property showAddressForm
   * @type     {Boolean}
   * @default  false
   */
  showAddressForm: false,

  /**
   * is the address1 input field required
   *
   * @property address1Required
   * @type     {Boolean}
   * @default  true
   */
  address1Required: true,

  /**
   * is the address2 input field required
   *
   * @property address2Required
   * @type     {Boolean}
   * @default  false
   */
  address2Required: false,

  /**
   * is the city required input field
   *
   * @property cityRequired
   * @type     {Boolean}
   * @default  true
   */
  cityRequired: true,

  /**
   * is the state input field required
   *
   * @property stateRequired
   * @type     {Boolean}
   * @default  true
   */
  stateRequired: true,

  /**
   * is the zip required input field
   *
   * @property zipRequired
   * @type     {Boolean}
   * @default  true
   */
  zipRequired: true,

  /**
   * if the 'mark as primary' button should show on the new address form
   *
   * @property markAsPrimary
   * @type     {Boolean}
   * @default  false
   */
  showMakePrimaryButton: false,

  /**
   * Translation for the select address modal title
   *
   * @property selectAddressTranslation
   * @type     {String}
   */
  selectAddressTranslation: t('address.titles.selectAddress'),

  /**
   * Translation for the new address modal title
   *
   * @property addAddressTranslation
   * @type     {String}
   */
  addAddressTranslation: t('address.titles.add'),

  /**
   * Computed property that checks to see if the required fields on the
   * proxyAddress are populated
   *
   * @method isNewAddressSubmitDisabled
   * @return {Boolean}
   */
  isNewAddressSubmitDisabled: computed('proxyAddress.{address1,address2,city,state,zip}', function() {
    return !!['address1', 'address2', 'city', 'state', 'zip'].find(addressField => {
      return this.get(`${addressField}Required`) && !this.get(`proxyAddress.${addressField}`);
    });
  }),

  /**
   * get the subscriber addresses and decide where to go
   * on the first route.
   *
   * @method willInsertElement
   */
  willInsertElement() {
    // do not worry about preloading if we are not using subscriber.addresses
    if(this.get('skipAddressSelect')) {
      if(this.get('skipBlock')) {
        this.send('showLiquidAddress');
      }
      this._super(...arguments);
      return;
    }

    // resolve the subscriber
    this.get('subscriber').then(subscriber => {
      // then the addresses so they can be counted
      subscriber.get('addresses').then(() => {
        if(this.get('skipBlock')) {
          this.send('showLiquidAddress');
        }
        this._super(...arguments);
      });
    });
  },

  /**
   * remove abandoned records left over from the new address form
   *
   * @method willDestroyElement
   */
  willDestroyElement() {
    if(this.get('proxyAddress.isNew')) {
      this.get('proxyAddress').rollbackAttributes();
    }
    this._super(...arguments);
  },

  /**
   * Assign the address to nested attributes, and disable
   * address editing by creating a new address
   *
   * @method setAddress
   * @param  {DS.Model} address to assign to resource
   */
  setAddress(selectedAddress) {
    if(!selectedAddress.get('isNew')) {
      // if the address is new, and assignment is enabled, allow the
      // address id to be on the record. this has to be handled by
      // the serializer. serializers/line for example.
      return resolve(this.set('address', selectedAddress));
    } else {
      // it is either a new address or an attempt to edit an address
      // addresses are immutable so copy properties to a new address
      return this.get('store').createRecord('address', {
        address1: selectedAddress.get('address1'),
        address2: selectedAddress.get('address2'),
        city: selectedAddress.get('city'),
        state: selectedAddress.get('state'),
        zip: selectedAddress.get('zip'),
        primary: selectedAddress.get('primary'),
        name: selectedAddress.get('name'),
        phoneNumber: selectedAddress.get('phoneNumber'),
        subscriber: this.get('subscriber')
      }).save().then(address => {
        this.set('address', address);
      });
    }
  },

  /**
   * Switches the 'state' of the component to one of the following:
   *
   * showBlock: Yield the content to the hosting template (default state)
   * showAddressForm: Display the blank address form for adding a new address
   * showAddressSelect: Show the address selection form with addresses on the
   * subscriber's account
   *
   * @method showState
   * @param  {String} state to switch to
   */
  showState(state) {
    let states = ['showBlock', 'showAddressForm', 'showAddressSelect'];
    // set all states to false
    states.forEach(st => this.set(st, false));
    // set the requested state to true
    this.set(state, true);
  },

  /**
   * Determines if the back button should be displayed. Used in cases like
   * skipping address selection, or if the purpose of the modal is entirely
   * to collect an address
   *
   * @property showBackButton
   * @type     {Boolean}
   */
  showBackButton: computed('skipBlock', 'showAddressForm', 'showAddressSelect', function() {
    // hide the back button on address select when skipBlock is true
    let hideBackOnAddressSelect = this.get('skipBlock') && this.get('showAddressSelect');

    // hide the back button on address form when skipBlock is true and the subscriber has no addresses to choose from
    // or the address select has been skipped on purpose (skipAddressSelect=true)
    let hideBackOnAddressForm = this.get('skipBlock') && (this.get('showAddressForm') && (!this.get('subscriber.hasAddress') || this.get('skipAddressSelect')));

    if(hideBackOnAddressSelect || hideBackOnAddressForm) {
      return false;
    }
    return true;
  }),

  actions: {
    /**
     * this is used by the select-address and new-address templates.
     * The proxyAddress record gets copied over to the address model
     * that was passed in originally.
     *
     * @method selectAddressAction
     * @param  {DS.Model} address
     */
    selectAddressAction(address) {
      // go back to the block state after an address is selected
      // or stay where you are and let the parent route handle the closing
      // of the modal
      if(!this.get('skipBlock')) {
        this.showState('showBlock');
      }
      this.setAddress(address).then(() => {
        // save the newly added address immediately for
        // models that do not support nested attributes for address records
        // i am looking at you delivery model.
        this.sendAction('selectAddressAction', this.get('address'));
      });
    },

    /**
     * Action triggers the state into showing the address form
     *
     * @method showNewAddressForm
     */
    showNewAddressForm() {
      this.showState('showAddressForm');
    },

    /**
     * Called when the yielded template has the address section clicked
     *
     * @method showLiquidAddress
     */
    showLiquidAddress() {
      // set proxy address to a new address record
      if(!this.get('proxyAddress')) {
        this.set('proxyAddress', this.get('store').createRecord('address'));
      }

      // if there are addresses
      if(this.get('subscriber.hasAddress') && !this.get('skipAddressSelect')) {
        // let them select one
        this.showState('showAddressSelect');
      } else {
        // if not, make them create one
        this.showState('showAddressForm');
      }
    },

    /**
     * if they are on the new address form, go back to the
     * address selection list, if not go back to the yielded
     *
     * @method goBack
     */
    goBack() {
      if(this.get('showAddressSelect') && this.get('skipBlock')) {
        this.send('closeModal');
      } else {
        // we are on address select, we want to go back to the yielded block
        this.showState('showBlock');
      }

      if(this.get('showAddressForm') && !this.get('skipAddressSelect')) {
        // we are on the address form we want to go back to address select
        this.showState('showAddressSelect');
      } else {
        this.showState('showBlock');
      }
    },

    /**
     * Bubbles the closeModal action out of the component
     *
     * @method closeModal
     */
    closeModal() {
      this.sendAction('closeModal');
    }
  }
});