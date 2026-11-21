"""Cliente de Ollama (el servidor de modelos local, dentro de Docker). Sin dependencias externas."""

import json
import os
import urllib.error
import urllib.request


class OllamaClient:
    def __init__(self, url=None, timeout=None):
        self.url = (url or os.environ.get('OLLAMA_URL') or 'http://host.docker.internal:11434').rstrip('/')
        self.timeout = timeout or float(os.environ.get('OLLAMA_TIMEOUT', 300))

    def _request(self, path, payload=None):
        data = None if payload is None else json.dumps(payload).encode()
        req = urllib.request.Request(self.url + path, data=data, headers={'Content-Type': 'application/json'})
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as response:
                return json.loads(response.read())
        except (urllib.error.URLError, OSError) as error:
            raise ConnectionError(f'Ollama is not reachable at {self.url}: {error}') from error

    def models(self):
        return [m['name'] for m in self._request('/api/tags').get('models', [])]

    def chat(self, model, messages, schema, seed=None):
        options = {'temperature': 0}
        if seed is not None:
            options['seed'] = int(seed)
        body = self._request('/api/chat', {
            'model': model, 'messages': messages, 'format': schema, 'stream': False, 'think': False, 'options': options,
        })

        return body.get('message', {}).get('content', '')
