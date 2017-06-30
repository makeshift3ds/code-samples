require "rails_helper"

describe Plan do
  subject { create(:plan) }

  it_behaves_like "a model with versioning"

  with_versioning do
    it "can show plan changes in a product's event log" do
      plan = create(:plan)

      els = EventLog.by_product_id(plan.product.id)
      expect(els.map(&:loggable_type)).to include "Plan"
    end
  end

  it "can have a path to a follow on plan for automatic top ups" do
    follow_on = create(:plan)
    plan = create(:plan, follow_on_path:
      { voice: follow_on.id, text: follow_on.id, data: follow_on.id })

    expect(plan.follow_on_path["voice"]).to eql follow_on.id
  end

  it "can't have diverging follow on paths" do
    voice_follow_on = create(:plan)
    text_follow_on = create(:plan)

    # Since the voice and text share a credit they must go to the same follow on
    plan = build(:plan,
      follow_on_path: { voice: voice_follow_on.id, text: text_follow_on.id },
      credits: [{ credit: 10.00, rates: { voice: 0.01, text: 0.01 } },
                { credit: 10.00, rates: { data: 0.10 } }])

    expect(plan).to_not be_valid
    expect(plan.errors).to_not be_empty
  end

  it "can't have a rate of zero" do
    plan = build(:plan, credits: [{ credit: 10.00, rates: { voice: 0 } }])

    expect(plan).to_not be_valid
    expect(plan.errors).to_not be_empty
  end

  it "can deal with empty hash follow on path" do
    plan = create(:plan, follow_on_path: {})
    expect(Plan.follow_on_plan(plan: plan, service_type: "voice")).to eql nil
  end

  describe "plan with no credits" do
    context "when a plan is a addon" do
      it "can have a no credit rates" do
        plan = build(:plan, add_on: true, credits: [])

        expect(plan).to be_valid
        expect(plan.errors).to be_empty
      end
    end

    context "when a plan is a base plan" do
      it "can't have a rate of zero" do
        plan = build(:plan, add_on: false, credits: [])

        expect(plan).to_not be_valid
        expect(plan.errors).to_not be_empty
        expect(plan.errors.first).to eql [:base, "Service credits must be provided for base plans"]
      end
    end
  end

  describe "services helpers" do
    let(:carrier)           { create(:carrier) }
    let(:data_service)      { create(:carrier_service, :provides_data, carrier: carrier) }
    let(:voice_service)     { create(:carrier_service, :provides_voice, carrier: carrier) }
    let(:other_service)     { create(:carrier_service, :provides_other, carrier: carrier) }
    let(:voicemail_service) { create(:carrier_service, :provides_voicemail, carrier: carrier) }

    let(:plan) do
      create(:plan,
       carrier_services: [voice_service, data_service, other_service],
       credits: [{ credit: 10.00, rates: { voice: 0.01 } },
                 { credit: 10.00, rates: { data: 0.10 } }])
    end

    subject { plan }

    its(:ephemeral_services) { eql [other_service] }
  end

  describe "plan_type" do
    context "when set to shared_plan_access" do
      before { subject.plan_type = "shared_plan_access" }

      it "does not allow shared_access plans to be shareable" do
        subject.shareable = true
        subject.credits = []
        expect(subject.valid?).to be false
        expect(subject.errors.full_messages.first).
          to match(/Shareable can not be enabled for plans that share access/)
      end

      it "does not allow plan_carrier_services" do
        subject.carrier_services << create(:carrier_service)
        subject.credits = []
        expect(subject.valid?).to be false
        expect(subject.errors.full_messages.first).
          to match(/Plans sharing access can not have carrier services/)
      end

      it "does not allow credits" do
        subject.credits = [{ "credit" => 10.0, "rates" => { "voice" => 0.01, "text" => 0.01 } }]
        expect(subject.valid?).to be false
        expect(subject.errors.full_messages.first).
          to match(/Plans sharing access can not have service buckets/)
      end
    end
  end
end