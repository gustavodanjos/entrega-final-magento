<?php
declare(strict_types=1);

namespace Nuvem\AtelieVitrine\Model;

use Magento\Customer\Model\Session;

class LikeIdentity
{
    /**
     * Initialize server-side session identity.
     *
     * @param Session $session
     * @param CreationLikes $likes
     */
    public function __construct(private readonly Session $session, private readonly CreationLikes $likes)
    {
    }

    /**
     * Keep visitor preferences across customer session regeneration at login.
     *
     * @return array
     */
    public function get(): array
    {
        $token = $this->session->getData('nuvem_like_visitor');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->setData('nuvem_like_visitor', $token);
        }
        $customerId = (int) $this->session->getCustomerId();
        if ($customerId > 0) {
            $this->likes->mergeGuestLikes($token, $customerId);
        }
        return ['customer_id' => $customerId, 'visitor_token' => $token];
    }
}
