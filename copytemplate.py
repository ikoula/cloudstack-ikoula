#!/usr/bin/env python

api_url = 'https://178.170.73.6/client/api'
apiKey = 'collez ici la clé api du compte destinataire'
secret = 'collez ici la clé secrète du compte destinataire'

import hashlib, hmac, string, base64, urllib
import json, urllib

class SignedAPICall(object):
    def __init__(self, api_url, apiKey, secret):
        self.api_url = api_url
        self.apiKey = apiKey
        self.secret = secret

    def request(self, args):
        args['apiKey'] = self.apiKey

        self.params = []
        self._sort_request(args)
        self._create_signature()
        self._build_post_request()

    def _sort_request(self, args):
        keys = sorted(args.keys())

        for key in keys:
            self.params.append(key + '=' + urllib.quote_plus(args[key]))

    def _create_signature(self):
        self.query = '&'.join(self.params)
        digest = hmac.new(
            self.secret,
            msg=self.query.lower(),
            digestmod=hashlib.sha1).digest()

        self.signature = base64.b64encode(digest)

    def _build_post_request(self):
        self.query += '&signature=' + urllib.quote_plus(self.signature)
        self.value = self.api_url + '?' + self.query

class CloudStack(SignedAPICall):
    def __getattr__(self, name):
        def handlerFunction(*args, **kwargs):
            if kwargs:
                return self._make_request(name, kwargs)
            return self._make_request(name, args[0])
        return handlerFunction

    def _http_get(self, url):
        response = urllib.urlopen(url)
        return response.read()

    def _make_request(self, command, args):
        args['response'] = 'json'
        args['command'] = command
        self.request(args)
        data = self._http_get(self.value)
        # The response is of the format {commandresponse: actual-data}
        key = command.lower() + "response"
        return json.loads(data)[key]

#Usage

api = CloudStack(api_url, apiKey, secret)
request = {'name': 'nom du template de votre choix','displaytext': 'nom du template de votre choix','ostypeid': 'Id du type de système utilisé par le template','url': 'Url http de téléchargement du VHD du template', 'zoneid': 'Id de la zone pour laquelle on veut que le template soit disponible','format': 'VHD','hypervisor': 'xenserver'}
result = api.registerTemplate(request)
print json.dumps ((result),sort_keys=True, indent=4, separators=(',', ': '))
