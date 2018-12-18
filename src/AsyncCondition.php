<?hh // strict
/* Copyright (c) 2015, Facebook, Inc.
 * All rights reserved.
 *
 *  This source code is licensed under the BSD-style license found in the
 *  LICENSE file in the root directory of this source tree. An additional grant
 *  of patent rights can be found in the PATENTS file in the same directory.
 */

namespace HH\Asio {

/**
 * A wrapper around ConditionWaitHandle that allows notification events
 * to occur before the condition is awaited.
 */
<<__ConsistentConstruct>>
class AsyncCondition<T> {
  private ?Awaitable<T> $condition = null;
  
  public function __construct() {}

  /**
   * Notify the condition variable of success and set the result.
   */
  final public function succeed(T $result): void {
    if ($this->condition === null) {
      $this->condition = async { return $result; };
    } else {
      invariant(
        $this->condition instanceof ConditionWaitHandle,
        'Unable to notify AsyncCondition twice',
      );
      $this->condition->succeed($result);
    }
  }

  /**
   * Notify the condition variable of failure and set the exception.
   */
  final public function fail(\Exception $exception): void {
    if ($this->condition === null) {
      $this->condition = async { throw $exception; };
    } else {
      invariant(
        $this->condition instanceof ConditionWaitHandle,
        'Unable to notify AsyncCondition twice',
      );
      $this->condition->fail($exception);
    }
  }
  
  final public function isNotified(): bool {
    return !\is_null($this->condition) && (!$this->condition instanceof ConditionWaitHandle || has_finished($this->condition));
  }

  /**
   * Asynchronously wait for the condition variable to be notified and
   * return the result or throw the exception received via notification.
   *
   * The caller must provide an Awaitable $notifiers that must not finish
   * before the notification is received. This means $notifiers must represent
   * work that is guaranteed to eventually trigger the notification. As long
   * as the notification is issued only once, asynchronous execution unrelated
   * to $notifiers is allowed to trigger the notification.
   */
  final public function gen(Awaitable<mixed> $notifiers): Awaitable<T> {
    if ($this->condition === null) {
      $this->condition = ConditionWaitHandle::create(async { await $notifiers; });
    }
    return $this->condition;
  }
  
  final public static function create((function(this): Awaitable<mixed>) $f): Awaitable<T> {
    $ret = new static();
    $core = $f($ret);
    return $ret->gen($core);
  }
  // final public static async function create_many((function((function(): this)): Awaitable<void>) $f): Awaitable<T> {
  //   $bell = new Pointer(new static());
  //   $running = new Pointer(false);
  //   $core = new NullablePointer();
  //   $children = vec[];
  //   $factory = () ==> {
  //     $running->set(true);
  //     if(!has_finished($bell->get()))
  //       $bell->get()->succeed(null);
      
  //     $target = new static();
  //     $_core = $core->get();
  //     if($_core !== null) {
  //       return $target;
  //     }
  //   };
  //   $core->set($f($factory));
  //   $lifetime->gen($core->get() ?? async {});
  //   while(true) {
      
  //   }
  // }
}

} // namespace HH\Asio
