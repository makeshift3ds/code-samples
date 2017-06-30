import asyncModule from 'employee-portal-cli/tests/helpers/util/async-module';
import {assertUniqueElement, assertFormErrors} from 'employee-portal-cli/tests/helpers/util/asserts';
import {formatMdn} from 'employee-portal-cli/helpers/format-mdn';
import {test} from 'qunit';

asyncModule('Acceptance: Subscriber - Line - New', {
  setup() {
    createTestObjects('integration', 'with_suretax', {})
    .then(() => createTestSubscribers())
    .then(() => createTestObjects('carrier', 'with_gsm', [{ name: 'GSM carrier' }]));
  }
});

test('can create a line', function() {
  togglePaperTrail(true);
  visit('/subscribers/1');
  click('.js-add-button');
  click('.js-add-line');
  // Use gsm carrier
  selectOption('.js-modal .js-carrier', 'GSM carrier');
  click('.js-service-address');
  click('.js-new-address');
  fillIn('.js-modal .js-zip', '33410');
  click('.js-submit-button');
  fillIn('.js-modal .js-iccid', '42');
  click('.js-submit-button');
  andThen(() => {
    // Should not have `meid`
    assertElementCount('.js-modal .js-meid', 0);
    // Should validate iccid
    assertText('.js-error-message', 'ICCID must be 19 or 20 decimal digits');
  });
  fillIn('.js-modal .js-iccid', '1234567890123456789');
  andThen(() => {
    assertElementCount('.js-error-message', 0);
  });
  // change carrier to cdma
  selectOption('.js-modal .js-carrier', '1 Way Wireless');
  andThen(() => {
    // Should show `meid` field
    assertElementCount('.js-modal .js-meid', 1);
    // `meid` validation
    assertElementCount('.js-error-message', 1);
    assertText('.js-error-message', 'MEID is required');
  });
  fillIn('.js-modal .js-meid', '1234');
  triggerEvent('.js-modal .js-meid', 'blur');
  andThen(() => {
    assertText('.js-error-message', 'MEID must be 8 or 14 hexadecimal digits or 11, 15 or 18 decimal digits.');
  });
  fillIn('.js-modal .js-meid', '00000000');
  click('.js-submit-button');

  // verify that event log was generated for line creation
  togglePaperTrail(false);
  andThen(() => {
    assertRoute('line.index.index');
    assertElement('.js-activity-view-all');
  });
  // verify that view all takes the user to the event log index
  click('.js-activity-view-all');
  andThen(() => {
    assertRoute('eventLogs.index');
    assertElementCount('.js-event-log-item', 1);
  });
});

test('can create a line with new activation and no porting', function(assert) {
  createTestCarriers();
  wait();
  visit('/subscribers/1/lines/new');
  assertFormErrors(assert);
  completeBasicLineFormData();

  // add service address
  click('.js-service-address');
  click('.js-new-address');
  fillInAddressData();
  click('.js-submit-button');
  andThen(() => {
    assertText('.js-service-address .js-address1', '123 Carbonite Way, Apartment 421');
    assertText('.js-service-address .js-city-state-zip', 'Bespin, FL 33410');
  });

  click('.js-submit-button');
  andThen(() => {
    assertRoute('line.index.index');
    assertText('.js-mdn', formatMdn('0000000000'));
    assertText('.js-meid-dec', '00000000000');
  });

  // can change line data after creation
  visit('/subscribers/1/lines/4');
  click('.js-change-line-info');
  // test gsm carrier `iccid` validation
  selectOption('.js-modal .js-carrier', 'GSM carrier');
  andThen(() => {
    // Should hide `meid` field
    assertElementCount('.js-modal .js-meid', 0);
  });
  andThen(() => {
    assertText('.js-error-message', 'ICCID is required');
  });
  fillIn('.js-modal .js-iccid', '1234');
  triggerEvent('.js-modal .js-iccid', 'blur');
  andThen(() => {
    assertText('.js-error-message', 'ICCID must be 19 or 20 digit');
  });
  fillIn('.js-modal .js-iccid', '1234567890123456789');
  triggerEvent('.js-modal .js-iccid', 'blur');
  andThen(() => {
    assertElementCount('.js-error-message', 0);
  });
  selectOption('.js-modal .js-carrier', '2 Way Wireless');
  andThen(() => {
    // Should show `meid` field again
    assertElementCount('.js-modal .js-meid', 1);
  });
  // test presence validation
  fillIn('.js-modal .js-meid', '');
  triggerEvent('.js-modal .js-meid', 'blur');
  andThen(() => {
    assertText('.js-error-message', 'MEID is required');
  });
  // test format validation
  fillIn('.js-modal .js-meid', '12345');
  triggerEvent('.js-modal .js-meid', 'blur');
  andThen(() => {
    assertText('.js-error-message', 'MEID must be 8 or 14 hexadecimal digits or 11, 15 or 18 decimal digits.');
  });
  fillIn('.js-modal .js-meid', '44324432');
  click('.js-service-address');
  click('.js-new-address');
  fillIn('.js-modal .js-address1', '456 Imperial Center Rd');
  fillIn('.js-modal .js-address2', 'Unit 108');
  fillIn('.js-modal .js-city', 'Coruscant');
  fillIn('.js-modal .js-zip', '33419');
  selectOption('.js-modal .js-state', 'Idaho');
  click('.js-modal .js-submit-button');
  andThen(() => {
    assertText('.js-service-address .js-address1', '456 Imperial Center Rd, Unit 108');
    assertText('.js-service-address .js-city-state-zip', 'Coruscant, ID 33419');
  });
  click('.js-modal .js-submit-button');
  andThen(() => {
    assertText('.js-meid-dec', '06803294258');
    assertText('.js-carrier-name', '2 Way Wireless');
  });

  // rollback works when cancelling
  click('.js-change-line-info');
  selectOption('.js-modal .js-carrier', '1 Way Wireless');
  click('.js-modal .js-cancel-button');
  andThen(() => {
    assertText('.js-carrier-name', '2 Way Wireless');
  });
});

test('can create a line with new activation and porting', function(assert) {
  visit('/subscribers/1/lines/new');
  completeBasicLineFormData();
  click('.js-service-address');
  click('.js-new-address');
  fillInAddressData();
  click('.js-submit-button');
  click('.js-porting');

  // continue button is disabled if mdn is invalid
  fillIn('.js-mdn', '123456');
  andThen(() => {
    assertUniqueElement(assert, '.js-submit-button.button--disabled');
  });

  fillIn('.js-mdn', '1234567890');
  click('.js-submit-button');

  // add carrier information
  fillIn('.js-carrier-account input', '12344321');
  fillIn('.js-carrier-password input', 'manatee');
  fillIn('.js-ssn input', '111-22-3333');
  fillIn('.js-first-name input', 'Wedge');
  fillIn('.js-last-name input', 'Antilles');
  click('.js-address');
  click('.js-new-address');

  // add address information
  fillInAddressData();
  click('.js-submit-button');
  // click save on carrier info
  click('.js-submit-button');
  // click save on new line modal
  click('.js-submit-button');
  andThen(() => {
    assertRoute('line.index.index');
    assertText('.js-mdn', formatMdn('0000000000'));
    assertText('.js-meid-dec', '00000000000');
    // verify port status
    assertText('.js-port-status', formatMdn('1234567890'));
    assertText('.js-port-status', 'submitted on activation');
  });

  // port status detail modal
  click('.js-port-details');
  andThen(() => {
    assertText('.js-id', 1);
    assertText('.js-status', 'New');
    assertText('.js-external-port-number', '');
    assertText('.js-mdn', formatMdn('1234567890'));
    assertText('.js-carrier', '1 Way Wireless');
    assertText('.js-address', '123 Carbonite Way, Apartment 421, Bespin, FL 33410');
    assertText('.js-ssn', '3333');
  });

  // edit number port
  click('.js-edit');
  fillIn('.js-modal .js-carrier-account', '11587654');
  fillIn('.js-modal .js-first-name', 'Biggs');
  fillIn('.js-modal .js-last-name', 'Darklighter');

  click('.js-address');
  click('.js-new-address');

  fillInAddressData();
  fillIn('.js-modal .js-address1', '456 Fifth Street');
  click('.js-modal .js-submit-button');
  click('.js-modal .js-submit-button');

  andThen(() => {
    assertRoute('line.index.index');
  });

  // cancel port
  visit('subscribers/1/lines/4/porting/carrier');
  andThen(() => {
    click('.js-cancel-port');
  });
  andThen(() => {
    click('.js-cancel-request');
  });
  andThen(() => {
    // cannot cancel a port in new state
    assertText('.js-port-status', formatMdn('1234567890'));
    assertText('.js-port-status', 'is cancelled');
  });
});

test('can create a line and purchase a digital product', function() {
  createTestObjects('warehouse', 'with_digital_products', [{
    name: 'Bespin Warehouse 1'
  }]);
  wait();

  // create an inactive line
  visit('/subscribers/1/lines/new');
  completeBasicLineFormData();
  click('.js-service-address');
  click('.js-new-address');
  fillInAddressData();
  click('.js-submit-button');
  click('.js-submit-button');

  // select a digital product to activate with
  visit('tools/catalog?airtime=true&line_id=4');
  click('.js-warehouse-product:first .js-add-product-link');

  // confirm the activation
  click('.js-complete-order');
  click('.js-submit-button');

  andThen(() => {
    assertRoute('subscriber.orders.order.index.index');
  });
  // TODO: testing does not create an activation
});

test('can remove a product from an airtime purchase', function() {
  createTestObjects('warehouse', 'with_digital_products', [{
    name: 'Bespin Warehouse 1'
  }]);
  wait();

  // select the first product in the list
  visit('tools/catalog?airtime=true&line_id=1');
  click('.js-add-product-link:first');

  // click the delete button in the product box
  click('.js-remove-product-link:first');

  // confirm the deletion
  click('.js-submit-button:last');

  andThen(() => {
    assertElementCount('.js-order-detail', 0);
  });
});

test('can add multiple products to an order', function() {
  createTestObjects('warehouse', 'with_products', [{
    name: 'Not Digital',
    number_of_products: 2
  }]);

  wait();

  andThen(() => {
    createTestObjects('warehouse', 'with_digital_products', [{
      name: 'Digital'
    }]);
    return wait();
  });

  visit('/tools/catalog?subscriber_id=1');

  // select the first product
  click('.js-warehouse-product:contains("Example Product #1") .js-add-product-link');

  // select the add product link
  click('.js-add-new-button');

  // select the second product
  click('.js-warehouse-product:contains("Example Product #2") .js-add-product-link');

  andThen(() => {
    click('.js-add-new-button');
  });

  andThen(() => {
    assertRoute('warehouseProducts.index.index');
  });
});

test('airtime and activation filter digital products', function() {
  createTestObjects('warehouse', 'with_products', [{
    name: 'Not Digital',
    number_of_products: 1
  }]);

  wait();

  andThen(() => {
    createTestObjects('warehouse', 'with_digital_products', [{
      name: 'Digital'
    }]);
    return wait();
  });

  // warehouseProducts list includes all products
  visit('tools/catalog');
  andThen(() => {
    assertElementCount('.js-warehouse-product', 4);
  });

  // airtime flag filters by digital
  visit('tools/catalog?airtime=true&subscriber_id=1');
  andThen(() => {
    assertElementCount('.js-warehouse-product', 3);
  });
});

test('can select existing service address for line', function() {
  createTestObjects('subscriber', 'with_address', [{
    first_name: "Firmus",
    last_name: "Piett"
  }]);
  wait();

  visit('/subscribers/4/lines/new');
  completeBasicLineFormData();

  // add service address
  click('.js-service-address');
  andThen(() => {
    assertElementCount('.js-address-list li', 1);
  });
  click('.js-address-list li');
  andThen(() => {
    assertText('.js-service-address .js-address1', '123 Example Rd, Suite 300');
    assertText('.js-service-address .js-city-state-zip', 'Example City, FL 33410');
  });
  click('.js-service-address');
  click('.js-new-address');
  fillInAddressData();
  click('.js-modal .js-submit-button');

  andThen(() => {
    assertText('.js-service-address .js-address1', '123 Carbonite Way, Apartment 421');
    assertText('.js-service-address .js-city-state-zip', 'Bespin, FL 33410');
  });
});

function fillInAddressData() {
  fillIn('.js-modal .js-address1', '123 Carbonite Way');
  fillIn('.js-modal .js-address2', 'Apartment 421');
  fillIn('.js-modal .js-city', 'Bespin');
  fillIn('.js-modal .js-zip', '33410');
  selectOption('.js-modal .js-state', 'Florida');
}

function completeBasicLineFormData() {
  selectOption('.js-modal .js-carrier', '1 Way Wireless');
  fillIn('.js-modal .js-meid', '00000000');
}