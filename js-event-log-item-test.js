import Ember from 'ember';
import {initialize as stringInit} from '../../../initializers/string';
import timestamp from '../../helpers/util/timestamp';
import resolver from '../../helpers/resolver';
import {moduleForComponent, test} from 'ember-qunit';
import {mockI18n} from 'ember-i18n-test-helpers';
const {getOwner} = Ember;

stringInit();
moduleForComponent('event-log-item', 'Component - Event Log Item', {
  needs: [
    'helper:format-date',
    'service:format-date'
  ],
  setup() {
    // Stub the global I18n
    Ember.I18n = {
      translations: {}
    };

    // mock i18n for component testing
    mockI18n().with({
      models: {
        eventLog: {
          create: "Added",
          by: "by",
          update: "update"
        }
      },
      moment: {
        calendarWithSeconds() {
          return {
            lastDay: '[Yesterday at] h:mm:ssA',
            sameDay: '[Today at] h:mm:ssA',
            nextDay: '[Tomorrow at] h:mm:ssA',
            lastWeek: 'L h:mm:ssA',
            nextWeek: 'dddd [at] h:mm:ssA',
            sameElse: 'L h:mm:ssA'
          };
        }
      }
    });
    getOwner(this).register('template:components/event-logs/event-item-li', resolver.resolve('template:components/event-logs/-event-item-li'));
  }
});

test('event-log-item handles create event', function(assert) {
  /* creates the component instance */
  let component = this.subject();

  /* assign a create event */
  Ember.run(() => {
    component.set('model', {
      event: 'create',
      versionedObjectType: 'User',
      changes: {
        firstName: ['nuthin', 'Bob'],
        active: [false, true]
      },
      user: {
        fullName: 'Joe Bob Bellybutton'
      },
      createdAt: timestamp(),
      updatedAt: timestamp()
    });
    // didInitAttrs fires when this.subject() is called
    // this refires it after the properties are set.
    component.didInitAttrs();
  });

  this.render();

  /* appends the component to the page */
  assert.equal(component.get('title'), 'User Added', 'title is generated');
  assert.equal(component.get('isLi'), true, 'li tagName is the default');
  assert.equal(component.get('isDiv'), false, 'div tagName is not the default');

  let changeList = component.get('propertyChangeList');
  assert.equal(changeList[0].attribute, 'First Name', 'attribute is translated and set on create');
  assert.equal(changeList[0].before, null, 'before is null on create');
  assert.equal(changeList[0].formattedBefore, null, 'formattedBefore is null on create');
  assert.equal(changeList[0].after, 'Bob', 'after is set on create');
  assert.equal(changeList[0].formattedAfter, 'Bob', 'formattedAfter is set on create');
  assert.equal(changeList[0].versionedObjectType, 'User', 'versionedObjectType is set on create');

  assert.equal(changeList[1].formattedAfter, 'Yes', 'true boolean is transformed into yes');
});

test('event-log-item handles update event', function(assert) {
  /* creates the component instance */
  let component = this.subject();

  /* assign a create event */
  Ember.run(() => {
    component.set('model', {
      event: 'update',
      versionedObjectType: 'User',
      changes: {
        firstName: ['Bob', 'Ken'],
        active: [true, false]
      },
      user: {
        fullName: 'Joe Bob Bellybutton'
      },
      createdAt: timestamp(),
      updatedAt: timestamp()
    });
    // didInitAttrs fires when this.subject() is called
    // this refires it after the properties are set.
    component.didInitAttrs();
  });

  /* appends the component to the page */
  this.render();

  let changeList = component.get('propertyChangeList');
  assert.equal(changeList[0].before, 'Bob', 'before is set on update');
  assert.equal(changeList[0].formattedBefore, 'Bob', 'formattedBefore is set on update');
  assert.equal(changeList[0].after, 'Ken', 'after is set on update');
  assert.equal(changeList[0].formattedAfter, 'Ken', 'formattedAfter is set on update');
  assert.equal(changeList[1].formattedAfter, 'No', 'false booleans are transformed into no');
});