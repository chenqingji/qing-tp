;(function(window, angular) {
  'use strict';
  
  window.upyun = new Upyun(window.Base64, window.md5);

  if (!window.JSON) throw new Error('JSON required.');
  if (!window.FormData) throw new Error('FormData required.');
  if (!window.XMLHttpRequest) throw new Error('XMLHttpRequest required.');
  if (angular) {
    angular.module('upyun', [
      'base64',
      'angular-md5'
    ]).factory('$upyun', function($base64, md5) {
      return new Upyun($base64, md5);
    });
  }

  function Upyun(base64, md5) {
    if (!base64) throw new Error('base64 required.');
    if (!md5) throw new Error('md5 required.');
    this.base64 = base64;
    this.md5 = md5;
    this.events = {};
    this.form_api_secret = '';
    this.configs = {};
    this.configs.expiration = (new Date().getTime()) + 60;
    

    this.configs['x-gmkerl-rotate'] = 'auto';
    this.configs['x-gmkerl-exif-switch'] = true;

    this.configs['allow-file-type'] = 'jpg,jpeg,gif,png';
  }

  Upyun.prototype.set = function(k, v) {
    var toplevel = ['form_api_secret', 'endpoint', 'host'];
    if (k && v) {
      if (toplevel.indexOf(k) > -1) {
        this[k] = v;
      } else {
        this.configs[k] = v;
      }
    }
    return this;
  };

  Upyun.prototype.on = function(event, callback) {
    if (event && callback) {
      this.events[event] = callback;
    }
    return this;
  };

  /*********************************/
  // Android native browser uploads blobs as 0 bytes, so we need a test for that
  var needsFormDataShim = (function () {
      var bCheck = ~navigator.userAgent.indexOf('Android')
                      && ~navigator.vendor.indexOf('Google')
                      && !~navigator.userAgent.indexOf('Chrome');
      return bCheck && navigator.userAgent.match(/AppleWebKit\/(\d+)/).pop() <= 534;
  })(),

  // Test for constructing of blobs using new Blob()
  blobConstruct = !!(function () {
      try { return new Blob(); } catch (e) {}
  })(),

  // Fallback to BlobBuilder (deprecated)
  XBlob = blobConstruct ? window.Blob : function (parts, opts) {
      var bb = new (window.BlobBuilder || window.WebKitBlobBuilder || window.MSBlobBuilder);
      parts.forEach(function (p) {
          bb.append(p);
      });

      return bb.getBlob(opts ? opts.type : undefined);
  };

  Upyun.prototype.XBlob = XBlob;

  function FormDataShim () {
    // Data to be sent
    this.parts = [];
    // Boundary parameter for separating the multipart values
    this.boundary = Array(21).join('-') + (+new Date() * (1e16*Math.random())).toString(36),
    this.append = function (name, value, filename) {
        this.parts.push('--' + this.boundary + '\r\nContent-Disposition: form-data; name="' + name + '"');
        if (value instanceof Blob) {
            this.parts.push('; filename="'+ (filename || 'blob') +'"\r\nContent-Type: ' + value.type + '\r\n\r\n');
            this.parts.push(value);
        } else {
            this.parts.push('\r\n\r\n' + value);
        }
        this.parts.push('\r\n');
    };
  }

  function SendCustomReq(req, formData) {
    if (formData instanceof FormDataShim) {
        var fr, data;
        // Append the final boundary string
        formData.parts.push('--' + formData.boundary + '--');

        // Create the blob
        data = new XBlob(formData.parts);

        // Set the multipart content type and boudary
        req.setRequestHeader('Content-Type', 'multipart/form-data; boundary=' + formData.boundary);

        // Set up and read the blob into an array to be sent
        fr = new FileReader();
        fr.onload = function () { req.send(fr.result); };
        fr.onerror = function (err) { throw err; };
        fr.readAsArrayBuffer(data);
    } else {
        req.send(formData);
    }
  }
  /*********************************/

  Upyun.prototype.upload = function(cid,params, paramsName, callback, progressCallBack) {
    if (!callback || typeof(callback) !== 'function') 
      throw new Error('callback function required.');

    var self = this;
    var req = new XMLHttpRequest();
    var uploadByForm = typeof(params) === 'string';
    var md5hash = self.md5.createHash || self.md5;
    var d = new Date();

    self.configs['save-key']='/upload/'+[d.getFullYear(),d.getMonth() + 1,d.getDate()].join("-")+'/'+cid+'/10'+(new Date()).valueOf()+"_"+Math.floor(Math.random()*10000)+'.jpeg';

    // if upload by form name,
    // all params must be input's value.

    var data = needsFormDataShim ? new FormDataShim() : (uploadByForm ?
      new FormData(document.forms.namedItem(file)) :
      new FormData());

    var policy = self.base64.encode(JSON.stringify(self.configs));
    var apiendpoint = self.endpoint || 'http://v0.api.upyun.com/' + self.configs.bucket;
    var imageHost = self.host || 'http://' + self.configs.bucket + '.b0.upaiyun.com';

    // by default, if not upload files by form,
    // file object will be parse as `params`
    if (!uploadByForm) data.append('file', params, paramsName);
    data.append('policy', policy);
    data.append('signature', md5hash(policy + '&' + self.form_api_secret));

    // open request
    req.open('POST', apiendpoint, true);

    // Error event
    req.addEventListener('error', function(err) {
      return callback(err);
    }, false);

    // when server response
    req.addEventListener('load', function(result) {
      var statusCode = result.target.status;
      // trying to parse JSON
      if (statusCode !== 200)
        return callback(new Error(result.target.status), result.target);
      try {
        var image = JSON.parse(this.responseText);
        image.absUrl = imageHost + image.url;
        image.absUri = image.absUrl;
        return callback(null, result.target, image);
      } catch (err) {
        return callback(err);
      }
    }, false);

    // the upload progress monitor
    req.upload.addEventListener('progress', function(pe) {
      if (!pe.lengthComputable || typeof progressCallBack!== 'function')
        return;
      progressCallBack(Math.round(pe.loaded / pe.total * 100));
    });

    // send data to server
    SendCustomReq(req, data);
  };

})(window, window.angular);
