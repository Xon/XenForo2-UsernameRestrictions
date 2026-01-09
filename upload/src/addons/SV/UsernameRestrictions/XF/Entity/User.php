<?php

namespace SV\UsernameRestrictions\XF\Entity;

use Exception;
use SV\StandardLib\Helper;
use XF\Repository\UserGroup as UserGroupRepository;
use XF\Util\Str;
use function is_string;
use function mb_strpos;
use function mb_strtolower;
use function preg_match;
use function preg_replace;
use function strcmp;
use function strlen;
use function strval;
use function substr;
use function trim;

class User extends XFCP_User
{
    /** @noinspection PhpMissingReturnTypeInspection */
    protected function verifyUsername(&$username)
    {
        // force to string
        $username = strval($username);

        $ret = parent::verifyUsername($username);
        if (!$ret || strlen($username) === 0)
        {
            return $ret;
        }

        // unconditionally prevent username's starting with [, as this breaks username tagging
        if (substr($username, 0, 1) == '[')
        {
            $this->error(\XF::phrase('please_enter_another_name_required_format'), 'username');

            return false;
        }

        if ($username === $this->getExistingValue('username'))
        {
            return true; // allow existing
        }

        $options = \XF::options();
        if (!($options->sv_ur_apply_to_admins ?? true) && !\XF::visitor()->is_admin)
        {
            return $ret;
        }

        $blockSubset = $options->sv_ur_block_group_subset ?? false;
        $usernameLowercase = mb_strtolower($username);

        $userGroupRepo = Helper::repository(UserGroupRepository::class);
        $groups = $userGroupRepo->findUserGroupsForList();

        if (\XF::$versionId >= 2030000)
        {
            $transliterate = [Str::class, 'transliterate'];
            $normalize = [Str::class, 'normalize'];
        }
        else
        {
            // XF2.2 doesn't have a great replacement for these
            /** @noinspection SpellCheckingInspection */
            $transliterate = ['utf8_romanize'];
            $normalize = ['utf8_deaccent'];
        }

        foreach ($groups as $group)
        {
            $groupName = mb_strtolower($this->standardizeWhiteSpace($group['title']));
            if (strcmp($groupName, $usernameLowercase) === 0)
            {
                $this->error(\XF::phrase('usernames_must_be_unique'), 'username');

                return false;
            }

            if ($blockSubset && (mb_strpos($groupName, $usernameLowercase, 0) === 0))
            {
                $this->error(\XF::phrase('usernames_must_be_unique'), 'username');

                return false;
            }

            // compare against romanized name to help reduce confusable issues
            $groupName = mb_strtolower($normalize($transliterate($groupName)));
            if (strcmp($groupName, $usernameLowercase) === 0)
            {
                $this->error(\XF::phrase('usernames_must_be_unique'), 'username');

                return false;
            }

            if ($blockSubset && (mb_strpos($groupName, $usernameLowercase, 0) === 0))
            {
                $this->error(\XF::phrase('usernames_must_be_unique'), 'username');

                return false;
            }
        }

        return $ret;
    }

    protected function standardizeWhiteSpace(string $text): string
    {
        $text = preg_replace('/\s+/u', ' ', $text);
        try
        {
            // if this matches, then \v isn't known (appears to be PCRE < 7.2) so don't strip
            if (!preg_match('/\v/', 'v'))
            {
                $newName = preg_replace('/\v+/u', ' ', $text);
                if (is_string($newName))
                {
                    $text = $newName;
                }
            }
        }
        catch (Exception $e)
        {
        }

        return trim($text);
    }
}
