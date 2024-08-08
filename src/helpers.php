<?php

function cache() {
    return new class() {

        private $data = [];
        private $file = 'cache.json';

        public function __construct() {
            
            if (file_exists($this->file))
                touch($this->file);

            $this->load();
        }

        public function put(string|int $key, mixed $value): void {
            $this->data[$key] = [
                'value' => $value,
                'expiry' => null
            ];
            $this->save();
        }

        public function get(string|int $key): mixed {
            if (!isset($this->data[$key])) {
                return null;
            }

            $item = $this->data[$key];
            if ($item['expiry'] !== null && time() > $item['expiry']) {
                $this->forget($key);
                return null;
            }

            return $item['value'];
        }

        public function remember(string|int $key, int $seconds, callable $callback): mixed {
            $value = $this->get($key);
            if ($value === null) {
                $value = $callback();
                $this->put($key, $value);
                $this->data[$key]['expiry'] = time() + $seconds;
                $this->save();
            }
            return $value;
        }

        public function forget(string|int $key): void {
            unset($this->data[$key]);
            $this->save();
        }

        private function save(): void {
            file_put_contents($this->file, json_encode($this->data));
        }

        private function load(): void {
            if (file_exists($this->file)) {
                $this->data = json_decode(file_get_contents($this->file), true) ?? [];
            }
        }
    };
}
