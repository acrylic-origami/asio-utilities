<?hh // strict
namespace HH\Asio;
class Pointer<T> {
	public function __construct(protected T $v) {}
	public function set(T $v): void { $this->v = $v; }
	public async function aset(Awaitable<T> $v): Awaitable<void> { $this->v = await $v; }
	public function get(): ?T { return $this->v; }
}