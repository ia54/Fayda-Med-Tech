import React from "react";
import Banner from "../../../components/shared/banner/Banner";
import { useGetCMSContentQuery } from "../../../redux/features/cms/cmsContent";
import cms from "../../../constants/cms";

const Privacy = () => {
  const { data, isLoading: isLoadingCMS } = useGetCMSContentQuery();
  const cmsData = data?.settings;
  console.log({ cmsData, isLoadingCMS });
  console.log(
    cmsData?.[cms.privacyPolicyPage.banner.privacyPolicyBannerRightDescription],
  );
  // console.log(
  //   JSON.stringify(
  //     cmsData?.[cms.privacyPolicyPage.banner.aboutBannerRightDescription],
  //   ),
  // );

  if (isLoadingCMS) {
    return <HeroSkeleton />;
  }

  const cmsImagePath =
    cmsData?.[cms.privacyPolicyPage.banner.privacyPolicyBannerBg];

  const bannerImage = cmsImagePath
    ? `${import.meta.env.VITE_API_BASE_URL}${cmsImagePath}`
    : "/images/about/aboutBanner.png"; // fallback
  return (
    <div>
      <Banner
        bannerImage={bannerImage}
        subtitle={
          cmsData?.[
            cms.privacyPolicyPage.banner.privacyPolicyBannerLeftMiniTitle
          ]
        }
        Fayda={
          cmsData?.[cms.privacyPolicyPage.banner.privacyPolicyBannerLeftTitle1]
        }
        med={
          cmsData?.[cms.privacyPolicyPage.banner.privacyPolicyBannerLeftTitle2]
        }
        title={
          cmsData?.[cms.privacyPolicyPage.banner.privacyPolicyBannerLeftTitle3]
        }
        description={
          cmsData?.[
            cms.privacyPolicyPage.banner.privacyPolicyBannerRightDescription
          ]
        }
        buttonText={
          cmsData?.[cms.privacyPolicyPage.banner.privacyPolicyBannerButtonText]
        }
      />

      <section className="sectionGap mt-[60px] max-w-3xl">
        <h2 className="text-2xl md:text-3xl font-trap text-[#103E46] mb-4">
          Overview
        </h2>
        <p className="para text-[#103E46] mb-4">
          We collect minimal information needed to deliver our services. We do
          not sell personal data. Access is restricted and logged to ensure
          security and accountability.
        </p>
        <h3 className="text-xl md:text-2xl font-trap text-[#103E46] mt-8 mb-3">
          What We Collect
        </h3>
        <ul className="list-disc pl-6 space-y-2 text-[#103E46]">
          <li>Basic contact details you share with us</li>
          <li>Usage analytics to improve product quality</li>
          <li>Support communications for troubleshooting</li>
        </ul>
        <h3 className="text-xl md:text-2xl font-trap text-[#103E46] mt-8 mb-3">
          How We Use Data
        </h3>
        <ul className="list-disc pl-6 space-y-2 text-[#103E46]">
          <li>Provide, maintain, and improve the platform</li>
          <li>Respond to support requests</li>
          <li>Communicate updates relevant to your account</li>
        </ul>
        <p className="para text-[#103E46] mt-8">
          For questions, contact us at privacy@fayda-med.com.
        </p>
      </section>
    </div>
  );
};

export default Privacy;
