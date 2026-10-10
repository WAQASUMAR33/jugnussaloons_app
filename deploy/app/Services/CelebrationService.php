<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Setting;
use Carbon\Carbon;

class CelebrationService
{
    /**
     * Retrieve and compile birthday and anniversary celebration metrics and lists.
     *
     * @return array
     */
    public static function getCelebrationsData(): array
    {
        $today = now()->startOfDay();
        $setting = Setting::getSettings();
        $salonName = $setting->brand_name ?: 'Jugnu Saloon';

        $accounts = Account::with('category')
            ->where(function ($query) {
                $query->whereNotNull('date_of_birth')
                      ->orWhereNotNull('date_of_anniversary');
            })
            ->get();

        $celebrants = collect();

        foreach ($accounts as $account) {
            $hasBirthday = !empty($account->date_of_birth);
            $hasAnniversary = !empty($account->date_of_anniversary);
            $cleanPhone = preg_replace('/[^0-9]/', '', $account->phone_no1 ?? '');

            if ($hasBirthday) {
                try {
                    $dob = Carbon::parse($account->date_of_birth);
                    $bdayThisYear = Carbon::create($today->year, $dob->month, $dob->day, 0, 0, 0);
                    if ($bdayThisYear->lt($today)) {
                        $nextBday = $bdayThisYear->copy()->addYear();
                    } else {
                        $nextBday = $bdayThisYear;
                    }
                    $daysUntilBday = (int) $today->diffInDays($nextBday, false);
                    $turningAge = $nextBday->year - $dob->year;

                    $account->days_until_birthday = $daysUntilBday;
                    $account->next_birthday = $nextBday;
                    $account->turning_age = $turningAge > 0 ? $turningAge : null;
                    $account->birthday_formatted = $dob->format('M d');

                    // Generate Birthday Wishing Message & WhatsApp URL
                    $bdayMilestone = $account->turning_age ? "Turning {$account->turning_age}" : 'Birthday';
                    $account->birthday_wishing_message = $setting->getBirthdayMessageFor($account->name, $bdayMilestone, $account->birthday_formatted);
                    $account->birthday_whatsapp_url = $cleanPhone ? "https://wa.me/{$cleanPhone}?text=" . rawurlencode($account->birthday_wishing_message) : null;
                } catch (\Exception $e) {
                    $account->days_until_birthday = null;
                }
            }

            if ($hasAnniversary) {
                try {
                    $anniv = Carbon::parse($account->date_of_anniversary);
                    $annivThisYear = Carbon::create($today->year, $anniv->month, $anniv->day, 0, 0, 0);
                    if ($annivThisYear->lt($today)) {
                        $nextAnniv = $annivThisYear->copy()->addYear();
                    } else {
                        $nextAnniv = $annivThisYear;
                    }
                    $daysUntilAnniv = (int) $today->diffInDays($nextAnniv, false);
                    $anniversaryYears = $nextAnniv->year - $anniv->year;

                    $account->days_until_anniversary = $daysUntilAnniv;
                    $account->next_anniversary = $nextAnniv;
                    $account->anniversary_years = $anniversaryYears > 0 ? $anniversaryYears : null;
                    $account->anniversary_formatted = $anniv->format('M d');

                    // Generate Marriage Ceremony Wishing Message & WhatsApp URL
                    $annivMilestone = $account->anniversary_years ? "{$account->anniversary_years} Year Marriage Anniversary" : 'Marriage Ceremony Anniversary';
                    $account->anniversary_wishing_message = $setting->getAnniversaryMessageFor($account->name, $annivMilestone, $account->anniversary_formatted);
                    $account->anniversary_whatsapp_url = $cleanPhone ? "https://wa.me/{$cleanPhone}?text=" . rawurlencode($account->anniversary_wishing_message) : null;
                } catch (\Exception $e) {
                    $account->days_until_anniversary = null;
                }
            }

            $celebrants->push($account);
        }

        // Today
        $todayBirthdays = $celebrants->filter(function ($a) {
            return isset($a->days_until_birthday) && $a->days_until_birthday === 0;
        })->values();

        $todayAnniversaries = $celebrants->filter(function ($a) {
            return isset($a->days_until_anniversary) && $a->days_until_anniversary === 0;
        })->values();

        // Upcoming in next 30 days
        $upcomingBirthdays = $celebrants->filter(function ($a) {
            return isset($a->days_until_birthday) && $a->days_until_birthday > 0 && $a->days_until_birthday <= 30;
        })->sortBy('days_until_birthday')->values();

        $upcomingAnniversaries = $celebrants->filter(function ($a) {
            return isset($a->days_until_anniversary) && $a->days_until_anniversary > 0 && $a->days_until_anniversary <= 30;
        })->sortBy('days_until_anniversary')->values();

        // This calendar month
        $thisMonthBirthdays = $celebrants->filter(function ($a) use ($today) {
            return !empty($a->date_of_birth) && Carbon::parse($a->date_of_birth)->month === $today->month;
        })->sortBy(function ($a) {
            return Carbon::parse($a->date_of_birth)->day;
        })->values();

        $thisMonthAnniversaries = $celebrants->filter(function ($a) use ($today) {
            return !empty($a->date_of_anniversary) && Carbon::parse($a->date_of_anniversary)->month === $today->month;
        })->sortBy(function ($a) {
            return Carbon::parse($a->date_of_anniversary)->day;
        })->values();

        // Combined Next Upcoming Celebrations (nearest up to 60 days, or next available celebrants)
        $combinedUpcoming = collect();

        foreach ($celebrants as $acc) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $acc->phone_no1 ?? '');

            if (isset($acc->days_until_birthday)) {
                $earlyBdayMsg = "Hello {$acc->name}! 🎂 Warm early birthday wishes from {$salonName}! We look forward to celebrating your birthday on {$acc->birthday_formatted}. Visit us to claim your exclusive celebration makeover & discount offer!";
                $combinedUpcoming->push([
                    'type' => 'birthday',
                    'account' => $acc,
                    'days_until' => $acc->days_until_birthday,
                    'next_date' => $acc->next_birthday,
                    'milestone' => $acc->turning_age ? "Turning {$acc->turning_age}" : 'Birthday',
                    'formatted_date' => $acc->birthday_formatted,
                    'wishing_message' => $acc->birthday_wishing_message,
                    'whatsapp_url' => $cleanPhone ? "https://wa.me/{$cleanPhone}?text=" . rawurlencode($earlyBdayMsg) : null,
                ]);
            }
            if (isset($acc->days_until_anniversary)) {
                $earlyAnnivMsg = "Hello {$acc->name}! 💍 Warm advance wishes from {$salonName} for your upcoming marriage ceremony anniversary on {$acc->anniversary_formatted}! Celebrate your special milestone with our luxury couple pampering packages!";
                $combinedUpcoming->push([
                    'type' => 'anniversary',
                    'account' => $acc,
                    'days_until' => $acc->days_until_anniversary,
                    'next_date' => $acc->next_anniversary,
                    'milestone' => $acc->anniversary_years ? "{$acc->anniversary_years} Year Anniversary" : 'Marriage Ceremony Anniversary',
                    'formatted_date' => $acc->anniversary_formatted,
                    'wishing_message' => $acc->anniversary_wishing_message,
                    'whatsapp_url' => $cleanPhone ? "https://wa.me/{$cleanPhone}?text=" . rawurlencode($earlyAnnivMsg) : null,
                ]);
            }
        }

        $sortedUpcoming = $combinedUpcoming->sortBy('days_until')->values();

        return [
            'todayBirthdays' => $todayBirthdays,
            'todayAnniversaries' => $todayAnniversaries,
            'upcomingBirthdays' => $upcomingBirthdays,
            'upcomingAnniversaries' => $upcomingAnniversaries,
            'thisMonthBirthdays' => $thisMonthBirthdays,
            'thisMonthAnniversaries' => $thisMonthAnniversaries,
            'allCelebrants' => $celebrants,
            'sortedUpcoming' => $sortedUpcoming,
            'totalTodayCelebrations' => $todayBirthdays->count() + $todayAnniversaries->count(),
            'totalUpcomingCelebrations' => $upcomingBirthdays->count() + $upcomingAnniversaries->count(),
            'totalMonthCelebrations' => $thisMonthBirthdays->count() + $thisMonthAnniversaries->count(),
            'totalRegisteredCelebrants' => $celebrants->count(),
        ];
    }
}
