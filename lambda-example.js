'use strict';

/**
 * Lambda function that is invoked when a record is added or modified in the assets table.
 * If the record has a stencil_id that is equal to a fire extinguisher, an Expiration Date
 * and a Note that parses down to a phone number (all non-numeric characters removed)
 * Then it will attempt to send an SMS to that phone number.
 * 
 * Lambda Url: https://console.aws.amazon.com/lambda/home?region=us-east-1#/functions/demoSendEmergencySMS?tab=graph
 * Cloudwatch (logs) Url: https://console.aws.amazon.com/cloudwatch/home?region=us-east-1#logStream:group=/aws/lambda/demoSendEmergencySMS;streamFilter=typeLogStreamPrefix
 * 
 */

const aws = require('aws-sdk');
const twilio = require('twilio');

const secretsService = require('aws-secrets-service');

// configuration
const FROM_NUMBER = '18132965706';
const FIRE_EXTINGUISHER_STENCIL_ID = 'frextgnshr';

exports.handler = async (event, context, callback) => {
  // this should never happen in lambda-land
  // not calling the callback would keep the lambda running
  if (!event || !context || !callback) {
    return 'handler called with a missing argument';
  }

  // exports.log('eventRecords', event.Records.length);

  // bail out if there are no records (again, should never happen)
  if (!event.Records || !event.Records.length) {
    return callback('no records sent from dynamodb to lambda');
  }

  // get the twilio credentials from aws secrets
  let secretsObj = await secretsService();
  let secrets = JSON.parse(secretsObj.SecretString);

  // create a twilio client
  let twilioClient = twilio(secrets.TWILIO_SID, secrets.TWILIO_SECRET);

  // go through any records that were sent and
  // send an SMS if the record matches the criteria below.
  event.Records.forEach(record => {
    // skip if it is not a fire extinguisher stencil
    if (getNestedValue(record, 'dynamodb.NewImage.stencil_Id.S') !== FIRE_EXTINGUISHER_STENCIL_ID) {
      exports.log('not a fire extinguisher');
      return;
    }

    // skip if it does not have idpAttributes
    if (!getNestedValue(record, 'dynamodb.NewImage.idpAttributes.S')) {
      return exports.log('does not have idpAttributes');
    }
 
    // encode the idpAttributes so we can access the optional object
    let idpAttributes = {};
    try {
      idpAttributes = JSON.parse(record.dynamodb.NewImage.idpAttributes.S);
    } catch (e) {
      exports.log('failed to convert idpAttributes json', e);
      return;
    }

    // skip attributes without an Expiration Date or a Note (aka phone number)
    if (!idpAttributes.optional || !idpAttributes.optional['Expiration Date'] || !idpAttributes.optional.Notes) {
      exports.log('did not send because it is missing Expiration Date or Note (aka phone number)', idpAttributes);
      return;
    }

    // try to format the Note like a phone number
    let phoneNumber = idpAttributes.optional.Notes.replace(/[^\w]/g, '');
    if (phoneNumber.length === 10) {
      phoneNumber = `1${phoneNumber}`;
    }

    // skip if the phone number does not appear valid
    if (phoneNumber.length !== 11) {
      exports.log('did not send because the note is not a valid phone number', phoneNumber);
      return;
    }

    exports.log('sending SMS based on this record', JSON.stringify(record, null, 2));
    try {
      // send the message through twilio
      twilioClient.messages.create({
        body: `A fire extinguisher at ${record.dynamodb.Keys.company.S} - ${record.dynamodb.NewImage.property.S} with the asset id #${record.dynamodb.NewImage.guid.S} is about to expire.`,
        from: '+${FROM_NUMBER}',
        // keeping this for posterity
        // mediaUrl: 'http://people.sc.fsu.edu/~jburkardt/data/png/baboon.png',
        to: `+${phoneNumber}`
      }).then(message => {
        exports.log('message sent successfully', message.sid);
        callback(null, 'success');
      }).catch(e => {
        exports.log('twilio request failed', e);
        callback(e, null);
      });
    } catch (e) {
      exports.log('twilio failed to send', e);
      return;
    }
  });
}

/**
 * get a deeply nested value without having to validate every layer
 * of the object.
 * 
 * @param {Object} obj object to search 
 * @param {String} str period seperated keys i.e. `key1.key2.key3`
 */
var getNestedValue = (obj, str) => {
  return str
    .split('.')
    .reduce((returnVal, currentKey) => {
      return returnVal && returnVal[currentKey] ? returnVal[currentKey] : null;
    }, obj);
}

/**
 * add an internal log function that proxies arguments to console.log
 * because lambdas write to CloudWatch using console.log
 * and that makes them chatty in the tests. Also it means
 * we can test that exports.log is being invoked.
 * 
 * @param {Mixed} args what is normally sent to console.log
 */
exports.log = (...args) => {
  console.log(...args);
}