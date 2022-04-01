<?php

namespace Drupal\samlauth;

/**
 * EMPTY CLASS.
 *
 * After writing up all considerations, I concluded that this wasn't needed yet.
 * So now this is just just a bunch of comments on plans for v4.x, for me to
 * circle back to - which might as well be committed into 3.x for anyone
 * curious about a future refactor. What I drafted so far:
 * ---
 * Something like the Auth class; can change methods/signatures at any time.
 *
 * Do not trust anything about this class to persist. I'm just creating it so I
 * don't have to refactor all uses in SamlService at the same time.
 *
 * Use case / reason for its existence: I want to refactor at least
 * processResponse() (i.e. the saml/acs path), in a way that requires us to not
 * use Auth anymore. Other saml/* paths may follow but I can't make up my mind
 * about that at the moment. (See "In the end" below.) Also, IF we do that... I
 * don't want to do all this refactoring in one go.
 *
 * Besides this 'refactoring issue', we also want(ed) to refactor
 * SamlService::reformatConfig() into a Settings subclass that does 'just in
 * time' reading of cert/key values only when necessary. That is currently not
 * possible IF we keep using the Auth class. So for now, we've postponed this
 * and instead have added extra logic to SamlService::reformatConfig(); see
 * comments there. We may extend that method (and/or move it in here) to return
 * a Settings child class, if that ever becomes applicable.
 *
 * Bigger story / context: the Auth class is named the PHP Toolkit's "main
 * class" but it doesn't fit some of the things we need to do and is hard to
 * override. Also, taking a step back it doesn't seem clear in what it wants to
 * be. On one hand it seems to be a service/helper class for all other callers
 * to use, but:
 * - It's rather rigid in the way it forces usage of its own Settings class.
 *   I'm not yet sure how to approach this / if this is an issue; see above &
 *   SamlService::reformatConfig() comments.
 * - processResponse() and processSLO() have strange mechanics around error
 *   conditions, which makes error handling by callers more complicated then it
 *   needs to be. (Some errors throw an exception; some just set an error
 *   property so you have to call getErrors() afterwards - _and/or_
 *   isAuthenticated(); it isn't specified well that those two things are
 *   interdependent.)
 * - It shields access from Response/LogoutResponse/LogoutRequest value objects
 *   and I need the Response object to pass around to event subscribers.
 * On the other hand it seems to be a value object itself, that explicitly
 * wants to replace/obsolete the use of Response/LogoutResponse/LogoutRequest
 * objects by callers, because it copies all their properties into its own
 * properties after processing. However I don't see why we'd need to use Auth
 * instead of those other objects:
 * - I'm not aware of any information inside those other objects that needs to
 *   be 'shielded' from callers.
 * - The Auth class is only usable as a value object after first calling some
 *   'processing' method inside the same object, which kind-of goes against the
 *   notion of a value object.
 * - Even then, only part of its values (accessible by getters) will be
 *   populated and valid - depending on which thing we've processed (being an
 *   ACS Response / Logout Response / Logout Request). It feels like using the
 *   individual object would just be less confusing in that regard.
 * Or maybe it just wants to be 'just an example class', which an implementer
 * can learn from while building their own? What points into this direction is
 * - the fact that Auth forces usage of its own Settings class combined with
 *   the fact that the Settings constructor has a second $spValidationOnly
 *   parameter which is not used by Auth.
 * Then again, probably not, because
 * - it is used in many examples in README.md
 * - it is called 'the main class'
 * - it contains too much useful logic we don't want to reimplement (mainly in
 *   login() / logout() I guess).
 *
 * In the end,
 * - I don't want to use the Auth object to pass around to event subscribers
 *   during the ACS process; I want the Response object which is much more of
 *   a 'targeted' value object. That means I'll need to reimplement
 *   Auth::processResponse() - which is fine because it doesn't contain that
 *   much logic and makes error handling easier.
 * - I'm not sure yet whether I want to reimplement Auth::login() / logout() /
 *   processSLO(). I'm alternating between "they contain valuable logic that we
 *   don't want to reimplement / we won't be able to compare our code to any
 *   future changes in the Auth object" and "reimplementing is easy / will
 *   actually make things clearer".
 *
 * Further notes:
 * - When we reimplement processResponse(), many Auth methods (getters) will
 *   stop working completely, and we won't have a use for them. This means the
 *   code replacing Auth::processResponse() should (likely) not be in a child
 *   class of Auth. (So we're not bound to the fact that we cannot use a
 *   Settings child class for the new code coming out of that refactoring.)
 *
 * We still need to choose whether we want to
 * - remove any use of the Auth object completely, so we can use a custom
 *   Settings child class that will do what we want; (Note if we go this route,
 *   we may want to consider whether we can open an upstream PR to the upstream
 *   library, to improve... anything that led us to this decision? And
 *   submitting such a PR should likely include looking at the code examples in
 *   README.md and checking whether our new approach could improve them?)
 * - get a small PR accepted upstream that will allow passing a custom Settings
 *   child class into the Auth constructor, so we could use the same Settings
 *   child class(es) for passing into both Auth and whatever code will replace
 *   Auth::processResponse();
 * - write extra logic outside a Settings child class, in some kind of factory
 *   method, to decide which cert/key values will be necessary, before
 *   instantiating Settings/Auth objects.
 *
 * These are too many considerations to oversee right now. In 8.x-3.3 /
 * SamlService::reformatConfig() we've already been working toward the last
 * point, because anything else isn't possible in the 3.x versions - and we
 * didn't want to do a full refactor, removing all usage of the original Auth
 * object (and then have to make a decision on all the above), just to
 * implement alternative key / cert storage.
 */
class AuthVolatile {

}
