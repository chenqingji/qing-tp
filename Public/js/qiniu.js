;(function(window, angular) {
    'use strict';

    var cid = 0;
    cid = document.getElementById('cid').innerHTML;

    window.qiniu = new Qiniu(window.Base64, window.md5);

    if (!window.JSON) throw new Error('JSON required.');
    if (!window.FormData) throw new Error('FormData required.');
    if (!window.XMLHttpRequest) throw new Error('XMLHttpRequest required.');
    if (angular) {
        angular.module('upyun', [
            'base64',
            'angular-md5'
        ]).factory('$upyun', function($base64, md5) {
            return new Qiniu($base64, md5);
        });
    }

    function Qiniu(base64, md5) {

        if (!base64) throw new Error('base64 required.');
        if (!md5) throw new Error('md5 required.');

        this.base64 = base64;
        this.md5 = md5;

        /* config */
        this.host = 'http://7xoctk.com2.z0.glb.qiniucdn.com/';
        this.endpoint = 'http://upload.qiniu.com';
        this.form_api_secret = 'tuqCSemBcrHiOyohM38jZ2Qo_Z9wjizb9NZd2KtA:Ljn6yRaFg_ZRzQbRBqQWTS4D_ts=:eyJzY29wZSI6ImNodWNodWFuZyIsImRlYWRsaW5lIjoxNzYzMjU4NDAwfQ==';

        this.configs = {};
        this.events = {};

        // 存放根目录
        this.configs['save-key'] = '/7niu_upload/'+cid+'/';

        //this.configs['x-gmkerl-rotate'] = 'auto';
        //this.configs['x-gmkerl-exif-switch'] = true;
    }

    Qiniu.prototype.set = function(k, v) {
        if(k == 'form_api_secret'){
            return this;
        }
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

    Qiniu.prototype.on = function(event, callback) {
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

    Qiniu.prototype.XBlob = XBlob;

    function FormDataShim () {
        // Data to be sent
        this.parts = [];
        // Boundary parameter for separating the multipart values
        this.boundary = Array(21).join('-') + (+new Date() * (1e16*Math.random())).toString(36),
            this.append = function (name, value, filename) {
                this.parts.push('--' + this.boundary + '\nContent-Disposition: form-data; name="' + name + '"');
                if (value instanceof Blob) {
                    this.parts.push('; filename="'+ (filename || 'blob') +'"\nContent-Type: ' + value.type + '\n\n');
                    this.parts.push(value);
                } else {
                    this.parts.push('\n\n' + value);
                }
                this.parts.push('\n');
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

    Qiniu.prototype.upload = function(params, paramsName, callback, progressCallBack) {
        if (!callback || typeof(callback) !== 'function')
            throw new Error('callback function required.');

        var self = this;
        var req = new XMLHttpRequest();
        var uploadByForm = typeof(params) === 'string';

        var data = needsFormDataShim ? new FormDataShim() : (uploadByForm ?
            new FormData(document.forms.namedItem(file)) :
            new FormData());

        // 上传地址
        var apiendpoint = self.endpoint;
        // 访问地址
        var imageHost = self.host;

        data['append']('key', self.configs['save-key'] + paramsName);
        data['append']('token', self.form_api_secret);
        if (!uploadByForm) data['append']('file', params, paramsName);

        req.open('POST', apiendpoint, true);

        req.upload.addEventListener('progress', function(pe) {
            if (!pe.lengthComputable || typeof progressCallBack!== 'function')
                return;
            progressCallBack(Math.round(pe.loaded / pe.total * 100));
        });

        req.addEventListener('error', function(err) {
            return callback(err);
        }, false);

        req.addEventListener('load', function(result) {
            var statusCode = result.target.status;

            if (statusCode !== 200){
                return callback(new Error(result.target.status), result.target);
            }

            try {
                var image = JSON.parse(this.responseText);
                image.absUrl = imageHost + image.key;
                image.absUri = image.absUrl;
                return callback(null, result.target, image);
            } catch (err) {
                return callback(err);
            }

        }, false);

        SendCustomReq(req, data);
    };

})(window, window.angular);