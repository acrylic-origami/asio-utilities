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
 * Asynchronous equivalent of mechanisms such as epoll(), poll() and select().
 *
 * Transforms Awaitables over time to one total Awaitable.
 */
final class Scheduler {
  private static ?AsyncConditionNode<mixed> $lastAdded;
  private static ?AsyncConditionNode<mixed> $lastNotified;
  private static ?AsyncConditionNode<mixed> $lastAwaited;
  private static ?Awaitable<mixed> $notifiers;
  private static bool $init = false;

  protected static function maybe_init(): void {
    if(!static::$init) {
      $head = new AsyncConditionNode();
      static::$lastAdded = $head;
      static::$lastNotified = $head;
      static::$lastAwaited = $head;
      static::$notifiers = async {};
      static::$init = true;
    }
  }

  public static function launch(Awaitable<mixed> $awaitable): void {
    static::maybe_init();
    invariant(
      static::$lastAdded !== null,
      'Unable to add item, iteration already finished',
    );

    // Create condition node representing pending event.
    static::$lastAdded = static::$lastAdded->addNext();

    // Make sure the next pending condition is notified upon completion.
    $awaitable = static::waitForThenNotify($awaitable);

    // Keep track of all pending events.
    static::$notifiers = v(vec[
      $awaitable,
      static::$notifiers ?? async {},
    ]);
  }

  public static function launchMulti(Traversable<Awaitable<mixed>> $awaitables): void {
    static::maybe_init();
    invariant(
      static::$lastAdded !== null,
      'Unable to add item, iteration already finished',
    );
    $last_added = static::$lastAdded;

    // Initialize new list of notifiers.
    $notifiers = vec[static::$notifiers ?? async {}];

    foreach ($awaitables as $awaitable) {
      // Create condition node representing pending event.
      $last_added = $last_added->addNext();

      // Make sure the next pending condition is notified upon completion.
      $notifiers[] = static::waitForThenNotify($awaitable);
    }

    // Keep track of all pending events.
    static::$lastAdded = $last_added;
    static::$notifiers = v($notifiers);
  }

  private static async function waitForThenNotify(
    Awaitable<mixed> $awaitable,
  ): Awaitable<void> {
    try {
      $result = await $awaitable;
      invariant(static::$lastNotified !== null, 'unexpected null');
      static::$lastNotified = static::$lastNotified->getNext();
      invariant(static::$lastNotified !== null, 'unexpected null');
      static::$lastNotified->succeed($result);
    } catch (\Exception $exception) {
      invariant(static::$lastNotified !== null, 'unexpected null');
      static::$lastNotified = static::$lastNotified->getNext();
      invariant(static::$lastNotified !== null, 'unexpected null');
      static::$lastNotified->fail($exception);
    }
  }

  public static async function run(): Awaitable<void> {
    while(true) {
      invariant(
        static::$lastAwaited !== null,
        'Unable to iterate, either nothing added or iteration already finished',
      );

      static::$lastAwaited = static::$lastAwaited->getNext();
      if (static::$lastAwaited === null) {
        // End of iteration, no pending events to await.
        static::$lastAdded = null;
        static::$lastNotified = null;
        return;
      }
      await static::$lastAwaited->gen(static::$notifiers ?? async {});
    }
  }
}

} // namespace HH\Asio
