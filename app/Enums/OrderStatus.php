<?php

namespace App\Enums;

enum OrderStatus: string
{
	case NEW = 'new';
	case PROCESSING = 'processing';
	case WAITING_CUSTOMER = 'waiting_customer';
	case CONTRACT_SENT = 'contract_sent';
	case AWAITING_PAYMENT = 'awaiting_payment';
	case CONFIRMED = 'confirmed';
	case REJECTED = 'rejected';
}
