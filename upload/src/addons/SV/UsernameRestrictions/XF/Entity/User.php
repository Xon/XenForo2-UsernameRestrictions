<?php

namespace SV\UsernameRestrictions\XF\Entity;

class User extends XFCP_User
{
    protected function verifyUsername(&$username)
    {
        $ret = parent::verifyUsername($username);
        if (empty($ret) || empty($username))
        {
            return $ret;
        }

        // unconditionally prevent username's starting with [, as this breaks username tagging
        if (substr($username, 0, 1) == '[')
        {
            $this->error(\XF::Phrase('please_enter_another_name_required_format'), 'username');

            return false;
        }

        $options = \XF::app()->options();
        if (!$options->sv_ur_apply_to_admins && !empty(\XF::visitor()->Admin))
        {
            return $ret;
        }

        $blockSubset = $options->sv_ur_block_group_subset;
        $username_lowercase = utf8_strtolower($username);

        /** @var \XF\Repository\UserGroup $userGroupRepo */
        $userGroupRepo = $this->em()->getRepository('XF:UserGroup');
        $groups = $userGroupRepo->findUserGroupsForList();

        foreach ($groups as $group)
        {
            $groupname = utf8_strtolower($this->standardizeWhiteSpace($group['title']));
            if (strcmp($groupname, $username_lowercase) === 0)
            {
                $this->error(\XF::phrase('usernames_must_be_unique'), 'username');

                return false;
            }

            if ($blockSubset && (utf8_strpos($groupname, $username_lowercase, 0) === 0))
            {
                $this->error(\XF::Phrase('usernames_must_be_unique'), 'username');

                return false;
            }

            // compare against romanized name to help reduce confusable issues
            $groupname = utf8_strtolower(utf8_deaccent(utf8_romanize($groupname)));
            if (strcmp($groupname, $username_lowercase) === 0)
            {
                $this->error(\XF::phrase('usernames_must_be_unique'), 'username');

                return false;
            }

            if ($blockSubset && (utf8_strpos($groupname, $username_lowercase, 0) === 0))
            {
                $this->error(\XF::Phrase('usernames_must_be_unique'), 'username');

                return false;
            }
        }

        return $ret;
    }

    function standardizeWhiteSpace($text)
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
        catch (\Exception $e)
        {
        }

        return trim($text);
    }
}
